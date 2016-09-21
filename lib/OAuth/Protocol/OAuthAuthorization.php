<?php

namespace FeideConnect\OAuth\Protocol;

use FeideConnect\OAuth\Exceptions\OAuthException;
use FeideConnect\OAuth\Messages;
use FeideConnect\OAuth\AccessTokenPool;
use FeideConnect\OAuth\AuthorizationUI;
use FeideConnect\OAuth\AuthorizationEvaluator;
use FeideConnect\OAuth\OAuthUtils;

use FeideConnect\HTTP\LocalizedTemplatedHTMLResponse;

use FeideConnect\Data\Models;

use FeideConnect\Data\StorageProvider;
use FeideConnect\Authentication\Authenticator;
use FeideConnect\Authentication\UserMapper;

use FeideConnect\Logger;
use FeideConnect\Exceptions\AuthProviderNotAccepted;

class OAuthAuthorization {

    protected $storage;
    protected $auth;
    protected $request;

    protected $isPassive;
    protected $maxage = null;

    protected $client = null;
    protected $user = null;
    protected $organization = null;
    protected $account = null;

    protected $aevaluator = null;
    protected $openidConnect;

    public function __construct(Messages\Message $request, $openidConnect) {

        $this->storage = StorageProvider::getStorage();

        $this->request = $request;
        $this->openidConnect = $openidConnect;

        $this->auth = new Authenticator();

        // echo 'About to require authentication'; var_dump($this->request); Exit;

        if ($this->request->client_id) {
            $this->auth->setClientID($request->client_id);
        }

        $this->isPassive = false;

        if (!($this->request instanceof Messages\AuthorizationRequest)) {
            throw new OAuthException('invalid_request', 'Could not undestand request object.');
        }


    }

    protected function checkClient() {


        if ($this->client !== null) {
            return;
        }

        $this->client = $this->storage->getClient($this->request->client_id);
        if ($this->client === null) {
            throw new OAuthException('invalid_client', 'Could not look up the specified client.');
        }

        Logger::debug('OAuth Processing Authorization request, resolved client of the request.', array(
            'client' => $this->client
        ));

    }


    /**
     * Ensure that the user is authenticated...
     */
    protected function authenticateUser() {

        if ($this->user !== null) {
            return null;
        }

        if ($this->isPassive) {
            $this->auth->passiveAuthentication($this->client, $this->maxage);
        } else {
            $response = $this->auth->requireAuthentication($this->maxage);
            if ($response !== null) {
                return $response;
            }
        }

        $this->account = $this->auth->getAccount();

        $this->organization = $this->account->getOrg();

        $usermapper = new UserMapper($this->storage);
        $this->user = $usermapper->getUser($this->account, true, true, false);

        // echo '<pre>'; print_r($user); exit;

        Logger::debug('OAuth Processing Authorization request, user is authenticated', array(
            'user' => $this->user
        ));

        return null;
    }


    protected function obtainAuthorization() {


        $redirect_uri = $this->aevaluator->getValidatedRedirectURI();


        $state = $this->request->getState();
        $scopesInQuestion = $this->aevaluator->getScopesInQuestion();


        $aui = new AuthorizationUI($this->client, $this->request, $this->account, $this->user, $this->aevaluator);

        if ($this->aevaluator->needsAuthorization() ) {
            if ($this->isPassive) {
                throw new OAuthException('access_denied', 'User has not authorized, and were unable to perform passive authorization', $state, $redirect_uri, $this->request->useHashFragment());
            }

        } else {
            if ($this->isPassive) {
                return null;
            }
        }

        if (isset($_REQUEST["verifier"])) {
            $verifier = $this->user->getVerifier();
            if ($verifier !== $_REQUEST["verifier"]) {
                throw new \Exception("Invalid verifier code.");
            }

            // echo '<pre>'; print_r($_REQUEST); exit;

            if (!isset($_REQUEST['bruksvilkar'])) {
                throw new \Exception('Bruksvilkår not accepted.');
            }
            if ($_REQUEST['bruksvilkar'] !== 'yes') {
                throw new \Exception('Bruksvilkår not accepted.');
            }

            $scopes_approved = explode(' ', $_REQUEST['approved_scopes']);
            $apigkScopesApproved = [];
            foreach ($_REQUEST as $name => $value) {
                if (preg_match('/^gk_approved_scopes_(.*)$/', $name, $matches)) {
                    $apigkScopesApproved[$matches[1]] = explode(' ', $value);
                }
            }
            $authorization = $this->aevaluator->getUpdatedAuthorization($scopes_approved, $apigkScopesApproved);

            // echo "<pre>";
            // print_r($user->getBasicUserInfo());
            // print_r($authorization->getAsArray()); exit;

            $this->user->usageterms = true;
            $this->user->updateUserBasics($this->account);

            $this->storage->saveAuthorization($authorization);

            $this->aevaluator->getAuthorization();
            if ($this->aevaluator->needsAuthorization() ) {
                return $aui->show();
            }


        } else {
            return $aui->show();
        }

        return null;


    }


    protected function validateAuthProvider() {


        $this->account->validateAuthProvider($this->client->getAuthProviders());

    }


    protected function preProcess() {
        $this->checkClient();

        if ($this->aevaluator === null) {
            $this->aevaluator = new AuthorizationEvaluator($this->storage, $this->client, $this->request, $this->user);
        }

        $redirect_uri = $this->aevaluator->getValidatedRedirectURI();
        $state = $this->request->getState();

        if ($this->aevaluator->getEffectiveScopes() === []) {
            throw new OAuthException('invalid_scope', 'None of the requested scopes are approved for this client');
        }

        // If SimpleSAML_Auth_State_exceptionId query parameter is set, then something failed
        // while performing authentication.
        if (!empty($_REQUEST['SimpleSAML_Auth_State_exceptionId'])) {
            // The most likely error is that we are not able to perform passive authentication.
            throw new OAuthException('access_denied', 'Unable to perform passive authentication [1]', $state, $redirect_uri, $this->request->useHashFragment());

        } else if (isset($_REQUEST['error']) && $_REQUEST['error'] === '1') {
            // The most likely error is that we are not able to perform passive authentication.
            throw new OAuthException('access_denied', 'Unable to perform passive authentication [2]', $state, $redirect_uri, $this->request->useHashFragment());
        }


        $res = $this->authenticateUser();
        if ($res !== null) {
            return $res;
        }

        $this->aevaluator->setUser($this->user);

        try {
            $this->validateAuthProvider();
        } catch (AuthProviderNotAccepted $a) {
            return (new LocalizedTemplatedHTMLResponse('authprovidernotaccepted'))->setData([]);
        }


        $res = $this->obtainAuthorization();
        if ($res !== null) {
            return $res;
        }

        Logger::info("User authenticated", [
            'client' => $this->client,
            'user' => $this->user,
            'source' => $this->account->getSourceID(),
        ]);

        $this->storage->updateLoginStats($this->client, $this->account->getSourceID());
    }

    protected function getIDToken() {
        $openid = new \FeideConnect\OpenIDConnect\OpenIDConnect();
        $iat = $this->account->getAuthInstant();
        // echo '<pre>iat'; print_r($iat); exit;
        $idtoken = $openid->getIDtoken($this->user->userid, $this->client->id, $iat);
        if (isset($this->request->nonce)) {
            $idtoken->set('nonce', $this->request->nonce);
        }
        return $idtoken;
    }


    public function process() {

        if ($this->openidConnect) {
            if ($this->request->isPassiveRequest()) {
                $this->isPassive = true;
            }

            if ($this->request->loginPromptRequested()) {
                // If forcer authentication is requested by prompt=login, we will transform this into an
                // requirement about a less than 60 (+skew) seconds old authentication session.
                $this->maxage = 60;

            } else if ($this->request->max_age && is_int($this->request->max_age)) {
                $this->maxage = $this->request->max_age;
                if ($this->maxage < 10) {
                    $this->maxage = 10;
                }
            }
        }
        $res = $this->preProcess();
        if ($res !== null) {
            return $res;
        }

        if ($this->openidConnect) {
            switch ($this->request->response_type) {
            case 'id_token token':

                return $this->processToken();

            case 'code':

                return $this->processCode();

            }
            throw new OAuthException('invalid_request', 'Unsupported response_type ' . $this->request->response_type . ". Supported values are 'id_token token' and 'code'");
        } else {
            switch ($this->request->response_type) {
            case 'token':
                return $this->processToken();

            case 'code':

                return $this->processCode();

            }
            throw new Exception('Unsupported response_type in request. Only supported code and token.');
        }


    }


    protected function processToken() {

        $redirect_uri = $this->aevaluator->getValidatedRedirectURI();
        $scopesInQuestion = $this->aevaluator->getScopesInQuestion();
        $apigkScopes = $this->aevaluator->getAPIGKscopes();
        if ($this->openidConnect) {
            $flow = "OpenID Connect implicit grant";
            $idtoken = $this->getIDToken();
            $idtokenEnc = $idtoken->getEncoded();
        } else {
            $flow = "implicit grant";
            $idtokenEnc = null;
        }

        $tokenresponse = OAuthUtils::generateTokenResponse(
            $this->client,
            $this->user,
            $scopesInQuestion,
            $apigkScopes,
            $flow,
            $this->request->state,
            $idtokenEnc
        );

        return $tokenresponse->getRedirectResponse($redirect_uri, true);

    }

    protected function processCode() {
        $scopesInQuestion = $this->aevaluator->getScopesInQuestion();
        $apigkScopes = $this->aevaluator->getAPIGKscopes();
        $redirectURI = $this->aevaluator->getValidatedRedirectURI();
        $idtoken = null;
        if ($this->openidConnect) {
            if (empty($this->request->redirect_uri)) {
                throw new OAuthException("invalid_request", "Missing OpenID Connect required parameter [redirect_uri] at the authorization endpoint");
            }
            $idtoken = $this->getIDToken();
        }

        $code = Models\AuthorizationCode::generate($this->client, $this->user, $redirectURI, $scopesInQuestion, $apigkScopes, $idtoken);
        $this->storage->saveAuthorizationCode($code);

        $authorizationresponse = Messages\AuthorizationResponse::generate($this->request, $code);

        Logger::debug('OAuth Authorization Code is now stored, and may be fetched via the token endpoint.', array(
            'user' => $this->user,
            'client' => $this->client,
            'code' => $code,
        ));

        return $authorizationresponse->getRedirectResponse($redirectURI);

    }



}
