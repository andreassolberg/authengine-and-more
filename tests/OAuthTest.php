<?php
namespace tests;
use FeideConnect\Router;
use FeideConnect\HTTP\JSONResponse;

putenv("AEENV=test");
if (getenv('AEENV') !== "test") {
	throw new \Exception("Not able to set environmentvariable for test environment.");
}

class OAuthTest extends \PHPUnit_Framework_TestCase {

	function __construct() {


		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
		$_SERVER['REQUEST_URI'] = '/foo';
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['SERVER_PROTOCOL'] = 'https';
		$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en-US';

	}

	public function testOAuthConfig() {
		$router = new Router();

		$response = $router->dispatchCustom('GET', '/oauth/config');
		$this->assertInstanceOf('FeideConnect\HTTP\JSONResponse', $response, 'Expected /oauth/config endpoint to return json');

		$data = $response->getData();

		$this->assertArrayHasKey('authorization', $data);
		$this->assertArrayHasKey('token', $data);

	}
}