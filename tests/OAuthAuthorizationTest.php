<?php
namespace tests;
require_once(__DIR__ . '/ssp_mock_helper.php');

use FeideConnect\OAuth\Messages;
use FeideConnect\OAuth\Protocol\OAuthAuthorization;

class OAuthAuthorizationTest extends DBHelper {

	protected $client;

	function setUp() {
		parent::setUp();
		$this->client = $this->client();
		$this->user = $this->user();
		$_SERVER['REQUEST_URI'] = '/foo';
		$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en-US';
	}

	private function doRun($request) {
		$request = new Messages\AuthorizationRequest($request);
		$auth = new OAuthAuthorization($request);
		return $auth->process();
	}

	public function testAuthorizationToAccountChooser() {
		$this->setExpectedExceptionRegExp(
		    'FeideConnect\Exceptions\RedirectException', '/^http:\/\/localhost\/accountchooser\?/'
		);
		$this->doRun(array(
			'response_type' => 'code',
			'client_id' => $this->client->id,
		));
	}


    public function testAuthorizationToConsent() {
		$_REQUEST['acresponse'] = '{"id": "https://idp.feide.no","subid":"example.org"}';

		$response = $this->doRun(array(
			'response_type' => 'code',
			'client_id' => $this->client->id,
		));
		$this->assertInstanceOf('FeideConnect\HTTP\LocalizedTemplatedHTMLResponse', $response, 'Expected /oauth/authorization endpoint to return html');

		$data = $response->getData();
		$this->assertArrayHasKey('posturl', $data);
		$this->assertEquals($data['posturl'], 'http://localhost/oauth/authorization');
		$this->assertArrayHasKey('needsAuthorization', $data);
		$this->assertEquals($data['needsAuthorization'], true);
    }

	public function testAuthorizationBadClientID() {
		try {
			$this->doRun(array(
				'response_type' => 'code',
				'client_id' => '00000000-0000-0000-0000-000000000000',
			));
			$this->assertEquals(true, false, "Did not raise exception as expected");
		} catch (\FeideConnect\OAuth\Exceptions\OAuthException $e) {
			$this->assertEquals('invalid_client', $e->code);
		} catch (\Exception $f) {
			$this->assertEquals("", $f);
		}
	}

	public function testBadVerifier() {
		$this->setExpectedException('\Exception');
		$_REQUEST['acresponse'] = '{"id": "https://idp.feide.no","subid":"example.org"}';
		$_REQUEST['verifier'] = 'ugle';
		$_REQUEST['bruksvilkar'] = 'yes';

		$this->doRun(array(
			'response_type' => 'code',
			'client_id' => $this->client->id,
			'redirect_uri' => 'http://example.org',
		));
	}

	public function testBruksvilkarMissing() {
		$this->setExpectedException('\Exception');
		$_REQUEST['acresponse'] = '{"id": "https://idp.feide.no","subid":"example.org"}';
		$_REQUEST['verifier'] = $this->user->getVerifier();

		$this->doRun(array(
			'response_type' => 'code',
			'client_id' => $this->client->id,
			'redirect_uri' => 'http://example.org',
		));
	}

	public function testBruksvilkarNotAccepted() {
		$this->setExpectedException('\Exception');
		$_REQUEST['acresponse'] = '{"id": "https://idp.feide.no","subid":"example.org"}';
		$_REQUEST['verifier'] = $this->user->getVerifier();
		$_REQUEST['bruksvilkar'] = 'no';

		$this->doRun(array(
			'response_type' => 'code',
			'client_id' => $this->client->id,
			'redirect_uri' => 'http://example.org',
		));
	}

    public function testAuthorizationToCode() {
		$_REQUEST['acresponse'] = '{"id": "https://idp.feide.no","subid":"example.org"}';
		$_REQUEST['verifier'] = $this->user->getVerifier();
		$_REQUEST['bruksvilkar'] = 'yes';

		$response = $this->doRun(array(
			'response_type' => 'code',
			'client_id' => $this->client->id,
			'redirect_uri' => 'http://example.org',
		));
		$this->assertInstanceOf('FeideConnect\HTTP\Redirect', $response, 'Expected /oauth/authorization endpoint to redirect');

//		var_export($response);
		$url = $response->getURL();
		$this->assertEquals("http", parse_url($url, PHP_URL_SCHEME));
		$this->assertEquals("example.org", parse_url($url, PHP_URL_HOST));
		$query = parse_url($url, PHP_URL_QUERY);
		parse_str($query, $params);
		$this->assertArrayHasKey('code', $params);
    }

    public function testAuthorizationToToken() {
		$_REQUEST['acresponse'] = '{"id": "https://idp.feide.no","subid":"example.org"}';
		$_REQUEST['verifier'] = $this->user->getVerifier();
		$_REQUEST['bruksvilkar'] = 'yes';

		$response = $this->doRun(array(
			'response_type' => 'token',
			'client_id' => $this->client->id,
			'redirect_uri' => 'http://example.org',
			'state' => '12354',
		));
		$this->assertInstanceOf('FeideConnect\HTTP\Redirect', $response, 'Expected /oauth/authorization endpoint to redirect');

//		var_export($response);
		$url = $response->getURL();
		$this->assertEquals("http", parse_url($url, PHP_URL_SCHEME));
		$this->assertEquals("example.org", parse_url($url, PHP_URL_HOST));
		$fragment = parse_url($url, PHP_URL_FRAGMENT);
		parse_str($fragment, $params);
		$this->assertArrayHasKey('access_token', $params);
		$this->assertArrayHasKey('token_type', $params);
		$this->assertArrayHasKey('expires_in', $params);
		$this->assertArrayHasKey('scope', $params);
		$this->assertArrayHasKey('state', $params);
		$this->assertEquals($params['state'], '12354');
		$this->assertEquals($params['token_type'], 'Bearer');
    }
}
