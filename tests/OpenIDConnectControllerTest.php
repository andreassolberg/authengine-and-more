<?php
namespace tests;

use FeideConnect\Controllers\OpenIDConnect;
use Prophecy;


class OpenIDConnectControllerTest extends DBHelper {
    private $user;

    public function getUser() {
        return $this->user;
    }

    public function testConfig() {
        $response = OpenIDConnect::config();
        $this->assertInstanceOf('\FeideConnect\HTTP\JSONResponse', $response);
        $this->assertEquals([
            'authorization_endpoint' => 'http://localhost/oauth/authorization',
            'token_endpoint' => 'http://localhost/oauth/token',
            'userinfo_endpoint' => 'http://localhost/oauth/userinfo',
            'jwks_uri' => 'http://localhost/openid/jwks',
            'issuer' => 'https://sa-test-auth.feideconnect.no',
            'service_documentation' => 'http://feideconnect.no/docs/gettingstarted/',
            'token_endpoint_auth_methods_supported' => ['client_secret_basic'],
            'token_endpoint_auth_signing_alg_values_supported' => ['RS256'],
            'ui_locales_supported' => ['en', 'no', 'nb', 'nn'],
        ], $response->getData());
    }

    public function testGetJWKs() {
        $response = OpenIDConnect::getJWKs();
        $this->assertInstanceOf('\FeideConnect\HTTP\JSONResponse', $response);
        $data = $response->getData();
        $this->assertArrayHasKey('jwk', $data);
        $jwk = $data['jwk'];
        foreach ($jwk as $d) {
            $this->assertArrayHasKey('kty', $d);
            $this->assertArrayHasKey('n', $d);
            $this->assertArrayHasKey('e', $d);
            $this->assertEquals('RSA', $d['kty']);
        }
    }

    protected function protector($scopes) {
        $apiprotector = $this->prophesize('\FeideConnect\OAuth\APIProtector');
        $returnThis = new Prophecy\Promise\CallbackPromise(['\FeideConnect\OAuth\APIProtector', 'get']);
        $apiprotector->requireClient()->will($returnThis)->shouldBeCalled();
        $apiprotector->requireUser()->will($returnThis)->shouldBeCalled();
        $apiprotector->requireScopes(['openid'])->will($returnThis)->shouldBeCalled();
        $apiprotector->getUser()->will([$this, "getUser"])->shouldBeCalled();
        $apiprotector->getScopes()->willReturn($scopes)->shouldBeCalled();
        \FeideConnect\OAuth\APIProtector::$instance = $apiprotector->reveal();
    }

    public function testUserInfoBasic() {
        $this->user = $this->user();
        $this->protector([]);
        $response = OpenIDConnect::userinfo();
        $this->assertInstanceOf('\FeideConnect\HTTP\JSONResponse', $response);
        $data = $response->getData();
        $this->assertEquals([
            'sub' => $this->user->userid,
            'connect-userid_sec' => [],
        ], $data);
    }

    public function testUserInfoBasicScopesNoInfo() {
        $this->user = $this->user();
        $this->protector(['userinfo-mail', 'userinfo-photo', 'userinfo-feide']);
        $response = OpenIDConnect::userinfo();
        $this->assertInstanceOf('\FeideConnect\HTTP\JSONResponse', $response);
        $data = $response->getData();
        $this->assertEquals([
            'sub' => $this->user->userid,
            'connect-userid_sec' => ['feide:testuser@example.org'],
        ], $data);
    }

    protected function fullUser() {
        $this->user = $this->user();
        $this->user->email = ['feide:example.org' => 'test.user@example.org'];
        $this->user->name = ['feide:example.org' => 'Test User'];
        $this->user->ensureProfileAccess(false);
    }

    public function testUserInfoFull() {
        $this->fullUser();
        $this->protector(['userinfo-mail', 'userinfo-photo', 'userinfo-feide']);
        $response = OpenIDConnect::userinfo();
        $this->assertInstanceOf('\FeideConnect\HTTP\JSONResponse', $response);
        $data = $response->getData();
        $this->assertEquals([
            'sub' => $this->user->userid,
            'connect-userid_sec' => ['feide:testuser@example.org'],
            'email' => 'test.user@example.org',
            'email_verified' => true,
            'name' => 'Test User',
            'picture' => 'https://api.feideconnect.no/userinfo/user/media/' . $this->user->getProfileAccess(),
        ], $data);
    }

    public function testUserInfoFiltered() {
        $this->fullUser();
        $this->protector([]);
        $response = OpenIDConnect::userinfo();
        $this->assertInstanceOf('\FeideConnect\HTTP\JSONResponse', $response);
        $data = $response->getData();
        $this->assertEquals([
            'sub' => $this->user->userid,
            'connect-userid_sec' => [],
            'name' => 'Test User',
        ], $data);
    }
    
    public function tearDown() {
        parent::tearDown();
        \FeideConnect\OAuth\APIProtector::$instance = null;
    }
}