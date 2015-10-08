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

    public function __construct(Messages\Message $request) {


        $this->storage = StorageProvider::getStorage();


        $this->request = $request;
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

    protected function evaluateStepUp($aevaluator) {

        // We are in the mid of processing the OAuth authorization
        if ($aevaluator->hasScopeInQuestion('openid')) {
            // Parse the incomming Authorization Request.
            $request = new \FeideConnect\OpenIDConnect\Messages\AuthorizationRequest($_REQUEST);
            Logger::info('Successfully parsed OpenID Connect Authorization Request.', array(
                'request' => $request->asArray()
            ));
            $pAuthorization = new \FeideConnect\OpenIDConnect\Protocol\OICAuthorization($request);

            return $pAuthorization->process();

        }
        return null;
    }


    protected function checkClient() {


        if ($this->client !== null) {
            return;
        }

        $this->client = $this->storage->getClient($this->request->client_id);
        if ($this->client === null) {
            throw new OAuthException('invalid_client', 'Could not look up the specified client.');
        }

        Logger::info('OAuth Processing Authorization request, resolved client of the request.', array(
            'client' => $this->client
        ));

    }


    /**
     * Ensure that the user is authenticated...
     */
    protected function authenticateUser() {

        if ($this->user !== null) {
            return;
        }

        $this->auth->requireAuthentication($this->isPassive, true, null, $this->maxage); // require($isPassive = false, $allowRedirect = false, $return = null
        $this->account = $this->auth->getAccount();

        $this->organization = $this->account->getOrg();

        $usermapper = new UserMapper($this->storage);
        $this->user = $usermapper->getUser($this->account, true, true, false);

        // echo '<pre>'; print_r($user); exit;

        Logger::info('OAuth Processing Authorization request, user is authenticated', array(
            'user' => $this->user
        ));

    }


    protected function obtainAuthorization() {


        $redirect_uri = $this->aevaluator->getValidatedRedirectURI();


        $state = $this->request->getState();
        $scopesInQuestion = $this->aevaluator->getScopesInQuestion();


        $aui = new AuthorizationUI($this->client, $this->request, $this->account, $this->user, $redirect_uri, $scopesInQuestion, $this->aevaluator, $this->organization);

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

            $authorization = $this->aevaluator->getUpdatedAuthorization();

            // echo "<pre>";
            // print_r($user->getBasicUserInfo());
            // print_r($authorization->getAsArray()); exit;

            $this->user->usageterms = true;
            $this->user->updateUserBasics($this->account);

            $this->storage->saveAuthorization($authorization);


        } else {
            return $aui->show();
        }

        return null;


    }


    protected function validateAuthProvider() {


        $this->account->validateAuthProvider($this->client->getAuthProviders());

    }


    public function process() {





        $this->checkClient();

        if ($this->aevaluator === null) {
            $this->aevaluator = new AuthorizationEvaluator($this->storage, $this->client, $this->request, $this->user);
        }



        $redirect_uri = $this->aevaluator->getValidatedRedirectURI();
        $state = $this->request->getState();



        // If SimpleSAML_Auth_State_exceptionId query parameter is set, then something failed
        // while performing authentication.
        if (!empty($_REQUEST['SimpleSAML_Auth_State_exceptionId'])) {
            // The most likely error is that we are not able to perform passive authentication.
            throw new OAuthException('access_denied', 'Unable to perform passive authentication [1]', $state, $redirect_uri, $this->request->useHashFragment());

        } else if (isset($_REQUEST['error']) && $_REQUEST['error'] === '1') {
            // The most likely error is that we are not able to perform passive authentication.
            throw new OAuthException('access_denied', 'Unable to perform passive authentication [2]', $state, $redirect_uri, $this->request->useHashFragment());
        }


        $stepup = $this->evaluateStepUp($this->aevaluator);
        if ($stepup !== null) {
            return $stepup;
        }



        $this->authenticateUser();
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







        switch ($this->request->response_type) {
            case 'token':

                return $this->processToken();

            case 'code':

                return $this->processCode();

        }

        throw new Exception('Unsupported response_type in request. Only supported code and token.');

    }


    protected function processToken() {

        $redirect_uri = $this->aevaluator->getValidatedRedirectURI();
        $scopesInQuestion = $this->aevaluator->getScopesInQuestion();

        $tokenresponse = OAuthUtils::generateTokenResponse(
            $this->client,
            $this->user,
            $scopesInQuestion,
            "implicit grant",
            $this->request->state
        );


        return $tokenresponse->sendRedirect($redirect_uri, true);

    }



    protected function processCode() {



        $scopesInQuestion = $this->aevaluator->getScopesInQuestion();
        $redirectURI = null;
        if (!empty($this->request->redirect_uri)) {
            $redirectURI = $this->request->redirect_uri;
        }

        $code = Models\AuthorizationCode::generate($this->client, $this->user, $redirectURI, $scopesInQuestion);
        $this->storage->saveAuthorizationCode($code);

        $authorizationresponse = Messages\AuthorizationResponse::generate($this->request, $code);

        Logger::info('OAuth Authorization Code is now stored, and may be fetched via the token endpoint.', array(
            'user' => $this->user,
            'client' => $this->client,
            'code' => $code,
            'authorizationresponse' => $authorizationresponse,
        ));

        return $authorizationresponse->sendRedirect($this->request->redirect_uri);

    }



}
