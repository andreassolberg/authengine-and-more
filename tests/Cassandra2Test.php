<?php

namespace tests;

use FeideConnect\Data\Models;

class Cassandra2Test extends DBHelper {

    /**
     * @covers \FeideConnect\Data\Repositories\Cassandra2::saveUser
     */
    public function testSaveUser() {
        $userid = Models\User::genUUID();
        $userid_sec = bin2hex(openssl_random_pseudo_bytes(8)) . '@example.org';

        $user = new Models\User($this->db);
        $user->userid = $userid;
        $user->userid_sec = [$userid_sec];
        $this->db->saveUser($user);

        $results = $this->db->rawQuery('SELECT userid, updated, userid_sec FROM "users" WHERE userid = :userid', ['userid' => new \Cassandra\Uuid($userid)]);
        $this->assertCount(1, $results);
        $storedUser = $results[0];
        $this->assertEquals($userid, $storedUser['userid']);
        $this->assertNotNull($storedUser['updated']);
        $this->assertEquals([$userid_sec], $storedUser['userid_sec']->values());

        $results = $this->db->rawQuery('SELECT userid_sec, userid FROM "userid_sec" WHERE userid_sec = :userid_sec', ['userid_sec' => $userid_sec]);
        $this->assertCount(1, $results);
        $storedUserIDSec = $results[0];
        $this->assertEquals($userid_sec, $storedUserIDSec['userid_sec']);
        $this->assertEquals(new \Cassandra\Uuid($userid), $storedUserIDSec['userid']);
    }

    /**
     * @covers \FeideConnect\Data\Repositories\Cassandra2::updateUserBasics
     */
    public function testUpdateUserBasics() {
        $userid = Models\User::genUUID();

        $this->db->rawExecute('INSERT INTO "users" ("userid", "aboveagelimit", "usageterms") VALUES(:userid, FALSE, FALSE)', ['userid' => new \Cassandra\Uuid($userid)]);

        $user = $this->db->getUserByUserid($userid);
        $user->aboveagelimit = true;
        $user->usageterms = true;
        $this->db->updateUserBasics($user);

        $results = $this->db->rawQuery('SELECT updated, aboveagelimit, usageterms FROM "users" WHERE userid = :userid', ['userid' => new \Cassandra\Uuid($userid)]);
        $this->assertCount(1, $results);
        $storedUser = $results[0];
        $this->assertNotNull($storedUser['updated']);
        $this->assertEquals(true, $storedUser['aboveagelimit']);
        $this->assertEquals(true, $storedUser['usageterms']);
    }

    /**
     * @covers \FeideConnect\Data\Repositories\Cassandra2::updateUserIDsecLastSeen
     */
    public function testUpdateUserIDsecLastSeen() {
        $userid = Models\User::genUUID();

        $this->db->rawExecute('INSERT INTO "users" ("userid") VALUES(:userid)', ['userid' => new \Cassandra\Uuid($userid)]);

        $user = $this->db->getUserByUserid($userid);
        $this->db->updateUserIDsecLastSeen($user, 'foo');

        $results = $this->db->rawQuery('SELECT userid_sec_seen FROM "users" WHERE userid = :userid', ['userid' => new \Cassandra\Uuid($userid)]);
        $this->assertCount(1, $results);
        $storedUser = $results[0];
        $this->assertArrayHasKey('foo', $storedUser['userid_sec_seen']);

        /* Make sure that userid_sec_seen is close to "now". */
        $currentTime = microtime(true);
        $this->assertGreaterThan($currentTime-5, $storedUser['userid_sec_seen']['foo']->microtime(true));
        $this->assertLessThan($currentTime+5, $storedUser['userid_sec_seen']['foo']->microtime(true));
    }

    /**
     * @covers \FeideConnect\Data\Repositories\Cassandra2::updateUserInfo
     */
    public function testUpdateUserInfo() {
        $userid = Models\User::genUUID();

        $this->db->rawExecute('INSERT INTO "users" ("userid", "name", "email") VALUES(:userid, :name, :email)', [
            'userid' => new \Cassandra\Uuid($userid),
            'name' => \Cassandra\Type::map(\Cassandra\Type::text(), \Cassandra\Type::text())->create(
                'foo', 'Foo Testesen',
                'bar', 'Bar Testesen'
            ),
            'email' => \Cassandra\Type::map(\Cassandra\Type::text(), \Cassandra\Type::text())->create(
                'foo', 'foo@example.org',
                'bar', 'bar@example.org'
            ),
        ]);

        $user = $this->db->getUserByUserid($userid);
        $user->setUserInfo('foo', 'Test Testesen', 'test@example.org', null, null);
        $user->selectedsource = 'foo';
        $this->db->updateUserInfo($user, 'foo', ['name', 'email']);

        $results = $this->db->rawQuery('SELECT updated, name, email, selectedsource FROM "users" WHERE userid = :userid', ['userid' => new \Cassandra\Uuid($userid)]);
        $this->assertCount(1, $results);
        $storedUser = $results[0];
        $this->assertNotNull($storedUser['updated']);
        $this->assertArrayHasKey('foo', $storedUser['name']);
        $this->assertEquals('Test Testesen', $storedUser['name']['foo']);
        $this->assertArrayHasKey('foo', $storedUser['email']);
        $this->assertEquals('test@example.org', $storedUser['email']['foo']);
        $this->assertArrayHasKey('bar', $storedUser['name']);
        $this->assertEquals('Bar Testesen', $storedUser['name']['bar']);
        $this->assertArrayHasKey('bar', $storedUser['email']);
        $this->assertEquals('bar@example.org', $storedUser['email']['bar']);
        $this->assertEquals('foo', $storedUser['selectedsource']);
    }

    /**
     * @covers \FeideConnect\Data\Repositories\Cassandra2::updateProfilePhoto
     */
    public function testUpdateProfilePhoto() {
        $userid = Models\User::genUUID();

        $this->db->rawExecute('INSERT INTO "users" ("userid", "profilephoto", "profilephotohash") VALUES(:userid, :profilephoto, :profilephotohash)', [
            'userid' => new \Cassandra\Uuid($userid),
            'profilephoto' => \Cassandra\Type::map(\Cassandra\Type::text(), \Cassandra\Type::blob())->create(
                'foo', new \Cassandra\Blob('fooimg'),
                'bar', new \Cassandra\Blob('barimg')
            ),
            'profilephotohash' => \Cassandra\Type::map(\Cassandra\Type::text(), \Cassandra\Type::text())->create(
                'foo', 'foohash',
                'bar', 'barhash'
            ),
        ]);

        $user = $this->db->getUserByUserid($userid);
        $user->setUserInfo('foo', null, null, 'newfooimg', 'newfoohash');
        $this->db->updateProfilePhoto($user, 'foo');

        $results = $this->db->rawQuery('SELECT updated, profilephoto, profilephotohash FROM "users" WHERE userid = :userid', ['userid' => new \Cassandra\Uuid($userid)]);
        $this->assertCount(1, $results);
        $storedUser = $results[0];
        $this->assertNotNull($storedUser['updated']);
        $this->assertArrayHasKey('foo', $storedUser['profilephoto']);
        $this->assertEquals('newfooimg', $storedUser['profilephoto']['foo']->toBinaryString());
        $this->assertArrayHasKey('foo', $storedUser['profilephotohash']);
        $this->assertEquals('newfoohash', $storedUser['profilephotohash']['foo']);
        $this->assertArrayHasKey('bar', $storedUser['profilephoto']);
        $this->assertEquals('barimg', $storedUser['profilephoto']['bar']->toBinaryString());
        $this->assertArrayHasKey('bar', $storedUser['profilephotohash']);
        $this->assertEquals('barhash', $storedUser['profilephotohash']['bar']);
    }

    /**
     * @covers \FeideConnect\Data\Repositories\Cassandra2::addUserIDsec
     */
    public function testAddUserIDsec() {
        $userid = Models\User::genUUID();
        $userid_sec_orig = bin2hex(openssl_random_pseudo_bytes(8)) . '@example.org';
        $userid_sec_new = bin2hex(openssl_random_pseudo_bytes(8)) . '@example.org';

        $this->db->rawExecute('INSERT INTO "users" ("userid", "userid_sec") VALUES(:userid, :userid_sec)', [
            'userid' => new \Cassandra\Uuid($userid),
            'userid_sec' => \Cassandra\Type::set(\Cassandra\Type::text())->create($userid_sec_orig),
        ]);

        $this->db->addUserIDsec($userid, $userid_sec_new);

        $results = $this->db->rawQuery('SELECT userid_sec FROM "users" WHERE userid = :userid', ['userid' => new \Cassandra\Uuid($userid)]);
        $this->assertCount(1, $results);
        $storedUser = $results[0];
        $this->assertContains($userid_sec_orig, $storedUser['userid_sec']);
        $this->assertContains($userid_sec_new, $storedUser['userid_sec']);

        $results = $this->db->rawQuery('SELECT userid_sec, userid FROM "userid_sec" WHERE userid_sec = :userid_sec', ['userid_sec' => $userid_sec_new]);
        $this->assertCount(1, $results);
        $storedUserIDSec = $results[0];
        $this->assertEquals($userid_sec_new, $storedUserIDSec['userid_sec']);
        $this->assertEquals(new \Cassandra\Uuid($userid), $storedUserIDSec['userid']);
    }

    /**
     * @covers \FeideConnect\Data\Repositories\Cassandra2::deleteUser
     */
    public function testDeleteUser() {
        $userid = Models\User::genUUID();
        $userid_sec_a = bin2hex(openssl_random_pseudo_bytes(8)) . '@example.org';
        $userid_sec_b = bin2hex(openssl_random_pseudo_bytes(8)) . '@example.org';

        $this->db->rawExecute('INSERT INTO "users" ("userid", "userid_sec") VALUES(:userid, :userid_sec)', [
            'userid' => new \Cassandra\Uuid($userid),
            'userid_sec' => \Cassandra\Type::set(\Cassandra\Type::text())->create($userid_sec_a, $userid_sec_b),
        ]);
        $this->db->rawExecute('INSERT INTO "userid_sec" ("userid_sec", "userid") VALUES(:userid_sec, :userid)', [
            'userid_sec' => $userid_sec_a,
            'userid' => new \Cassandra\Uuid($userid),
        ]);
        $this->db->rawExecute('INSERT INTO "userid_sec" ("userid_sec", "userid") VALUES(:userid_sec, :userid)', [
            'userid_sec' => $userid_sec_b,
            'userid' => new \Cassandra\Uuid($userid),
        ]);

        $user = $this->db->getUserByUserid($userid);
        $this->db->deleteUser($user);

        $results = $this->db->rawQuery('SELECT userid FROM "users" WHERE userid = :userid', ['userid' => new \Cassandra\Uuid($userid)]);
        $this->assertCount(0, $results);
        $results = $this->db->rawQuery('SELECT userid_sec, userid FROM "userid_sec" WHERE userid_sec = :userid_sec', ['userid_sec' => $userid_sec_a]);
        $this->assertCount(0, $results);
        $results = $this->db->rawQuery('SELECT userid_sec, userid FROM "userid_sec" WHERE userid_sec = :userid_sec', ['userid_sec' => $userid_sec_b]);
        $this->assertCount(0, $results);
    }

    /**
     * @covers \FeideConnect\Data\Repositories\Cassandra2::getUserByUserID
     */
    public function testGetUserByUserID() {
        $userid = Models\User::genUUID();
        $userid_sec = bin2hex(openssl_random_pseudo_bytes(8)) . '@example.org';

        $this->db->rawExecute('INSERT INTO "users" ("userid", "created", "updated", "name", "email", "profilephoto", "profilephotohash", "selectedsource", "aboveagelimit", "usageterms", "userid_sec", "userid_sec_seen") VALUES(:userid, :created, :updated, :name, :email, :profilephoto, :profilephotohash, :selectedsource, :aboveagelimit, :usageterms, :userid_sec, :userid_sec_seen)', [
            'userid' => new \Cassandra\Uuid($userid),
            'created' => new \Cassandra\Timestamp(1122334455, 667000),
            'updated' => new \Cassandra\Timestamp(1234567890, 123000),
            'name' => \Cassandra\Type::map(\Cassandra\Type::text(), \Cassandra\Type::text())->create(
                'foo', 'Foo Testesen',
                'bar', 'Bar Testesen'
            ),
            'email' => \Cassandra\Type::map(\Cassandra\Type::text(), \Cassandra\Type::text())->create(
                'foo', 'foo@example.org',
                'bar', 'bar@example.org'
            ),
            'profilephoto' => \Cassandra\Type::map(\Cassandra\Type::text(), \Cassandra\Type::blob())->create(
                'foo', new \Cassandra\Blob('fooimg'),
                'bar', new \Cassandra\Blob('barimg')
            ),
            'profilephotohash' => \Cassandra\Type::map(\Cassandra\Type::text(), \Cassandra\Type::text())->create(
                'foo', 'foohash',
                'bar', 'barhash'
            ),
            'selectedsource' => 'foo',
            'aboveagelimit' => false,
            'usageterms' => false,
            'userid_sec' => \Cassandra\Type::set(\Cassandra\Type::text())->create($userid_sec),
            'userid_sec_seen' => \Cassandra\Type::map(\Cassandra\Type::text(), \Cassandra\Type::timestamp())->create(
                $userid_sec, new \Cassandra\Timestamp(1234567890, 123000)
            ),
        ]);

        $user = $this->db->getUserByUserID($userid);

        $this->assertNotNull($user);
        $this->assertEquals($userid, $user->userid);
        $this->assertArrayHasKey('foo', $user->name);
        $this->assertEquals('Foo Testesen', $user->name['foo']);
        $this->assertArrayHasKey('bar', $user->name);
        $this->assertEquals('Bar Testesen', $user->name['bar']);
        $this->assertArrayHasKey('foo', $user->email);
        $this->assertEquals('foo@example.org', $user->email['foo']);
        $this->assertArrayHasKey('bar', $user->email);
        $this->assertEquals('bar@example.org', $user->email['bar']);
        $this->assertArrayHasKey('foo', $user->profilephoto);
        $this->assertEquals('fooimg', $user->profilephoto['foo']);
        $this->assertArrayHasKey('bar', $user->profilephoto);
        $this->assertEquals('barimg', $user->profilephoto['bar']);
        $this->assertArrayHasKey('foo', $user->profilephotohash);
        $this->assertEquals('foohash', $user->profilephotohash['foo']);
        $this->assertArrayHasKey('bar', $user->profilephotohash);
        $this->assertEquals('barhash', $user->profilephotohash['bar']);
        $this->assertEquals([$userid_sec], $user->userid_sec);
        $this->assertEquals([$userid_sec => 1234567890123], $user->userid_sec_seen);
        $this->assertEquals('foo', $user->selectedsource);
        $this->assertEquals(false, $user->aboveagelimit);
        $this->assertEquals(false, $user->usageterms);
        $this->assertInstanceOf(\FeideConnect\Data\Types\Timestamp::class, $user->created);
        $this->assertEquals(1122334455667, (int)($user->created->getValue()*1000));
        $this->assertInstanceOf(\FeideConnect\Data\Types\Timestamp::class, $user->updated);
        $this->assertEquals(1234567890123, (int)($user->updated->getValue()*1000));
    }

    /**
     * @covers \FeideConnect\Data\Repositories\Cassandra2::getUserByUserIDsec
     */
    public function testGetUserByUserIDsec() {
        $userid = Models\User::genUUID();
        $userid_sec = bin2hex(openssl_random_pseudo_bytes(8)) . '@example.org';

        $this->db->rawExecute('INSERT INTO "users" ("userid", "userid_sec") VALUES(:userid, :userid_sec)', [
            'userid' => new \Cassandra\Uuid($userid),
            'userid_sec' => \Cassandra\Type::set(\Cassandra\Type::text())->create($userid_sec),
        ]);
        $this->db->rawExecute('INSERT INTO "userid_sec" ("userid_sec", "userid") VALUES(:userid_sec, :userid)', [
            'userid_sec' => $userid_sec,
            'userid' => new \Cassandra\Uuid($userid),
        ]);

        $user = $this->db->getUserByUserIDsec($userid_sec);
        $this->assertNotNull($user);
        $this->assertEquals($userid, $user->userid);
    }

    /**
     * @covers \FeideConnect\Data\Repositories\Cassandra2::getUserByUserIDsecList
     */
    public function testGetUserByUserIDsecList() {
        $userid_a = Models\User::genUUID();
        $userid_sec_a = bin2hex(openssl_random_pseudo_bytes(8)) . '@example.org';
        $userid_b = Models\User::genUUID();
        $userid_sec_b = bin2hex(openssl_random_pseudo_bytes(8)) . '@example.org';

        $this->db->rawExecute('INSERT INTO "users" ("userid", "userid_sec") VALUES(:userid, :userid_sec)', [
            'userid' => new \Cassandra\Uuid($userid_a),
            'userid_sec' => \Cassandra\Type::set(\Cassandra\Type::text())->create($userid_sec_a),
        ]);
        $this->db->rawExecute('INSERT INTO "userid_sec" ("userid_sec", "userid") VALUES(:userid_sec, :userid)', [
            'userid_sec' => $userid_sec_a,
            'userid' => new \Cassandra\Uuid($userid_a),
        ]);
        $this->db->rawExecute('INSERT INTO "users" ("userid", "userid_sec") VALUES(:userid, :userid_sec)', [
            'userid' => new \Cassandra\Uuid($userid_b),
            'userid_sec' => \Cassandra\Type::set(\Cassandra\Type::text())->create($userid_sec_b),
        ]);
        $this->db->rawExecute('INSERT INTO "userid_sec" ("userid_sec", "userid") VALUES(:userid_sec, :userid)', [
            'userid_sec' => $userid_sec_b,
            'userid' => new \Cassandra\Uuid($userid_b),
        ]);

        $users = $this->db->getUserByUserIDsecList([$userid_sec_a, $userid_sec_b]);
        $this->assertCount(2, $users);
        $this->assertTrue($users[0]->userid === $userid_a || $users[1]->userid === $userid_a);
        $this->assertTrue($users[0]->userid === $userid_b || $users[1]->userid === $userid_b);

        $users = $this->db->getUserByUserIDsecList(['incorrect_userid_here']);
        $this->assertNull($users);

        $users = $this->db->getUserByUserIDsecList([$userid_sec_a, 'incorrect_userid_here']);
        $this->assertCount(1, $users);
        $this->assertEquals($userid_a, $users[0]->userid);
    }

    /**
     * @covers \FeideConnect\Data\Repositories\Cassandra2::getAPIGK
     */
    public function testGetAPIGK() {
        $id = bin2hex(openssl_random_pseudo_bytes(8));
        $owner_uuid = \FeideConnect\Data\Model::genUUID();

        $this->db->rawExecute('INSERT INTO "apigk" ("id", "descr", "endpoints", "expose", "httpscertpinned", "name", "owner", "organization", "scopes", "requireuser", "scopedef", "privacypolicyurl", "status", "created", "updated") VALUES(:id, :descr, :endpoints, :expose, :httpscertpinned, :name, :owner, :organization, :scopes, :requireuser, :scopedef, :privacypolicyurl, :status, :created, :updated)', [
            'id' => $id,
            'descr' => 'Test API GK description',
            'endpoints' => \Cassandra\Type::collection(\Cassandra\Type::text())->create(
                'https://foo.example.org/bar'
            ),
            'expose' => 'expose-value',
            'httpscertpinned' => 'httpscertpinned-value',
            'name' => 'Test API GK name',
            'owner' => new \Cassandra\Uuid($owner_uuid),
            'organization' => 'Foo Inc.',
            'scopes' => \Cassandra\Type::set(\Cassandra\Type::text())->create('foo-scope'),
            'requireuser' => true,
            'scopedef' => json_encode([ 'scopedef-key' => 'scopedef-value' ]),
            'privacypolicyurl' => 'https://foo.example.org/privacy-policy',
            'status' => \Cassandra\Type::set(\Cassandra\Type::text())->create('status-value'),
            'created' => new \Cassandra\Timestamp(1122334455, 667000),
            'updated' => new \Cassandra\Timestamp(1234567890, 123000),
        ]);

        $gk = $this->db->getAPIGK($id);
        $this->assertNotNull($gk);
        $this->assertEquals('Test API GK name', $gk->name);
        $this->assertEquals('Test API GK description', $gk->descr);
        $this->assertEquals($owner_uuid, $gk->owner);
        $this->assertEquals('Foo Inc.', $gk->organization);
        $this->assertEquals(['https://foo.example.org/bar'], $gk->endpoints);
        $this->assertEquals('expose-value', $gk->expose);
        $this->assertEquals('httpscertpinned-value', $gk->httpscertpinned);
        $this->assertTrue($gk->requireuser);
        $this->assertArrayHasKey('scopedef-key', $gk->scopedef);
        $this->assertEquals('scopedef-value', $gk->scopedef['scopedef-key']);
        $this->assertEquals(['foo-scope'], $gk->scopes);
        $this->assertEquals('https://foo.example.org/privacy-policy', $gk->privacypolicyurl);
        $this->assertEquals(['status-value'], $gk->status);
        $this->assertInstanceOf(\FeideConnect\Data\Types\Timestamp::class, $gk->created);
        $this->assertEquals(1122334455667, (int)($gk->created->getValue()*1000));
        $this->assertInstanceOf(\FeideConnect\Data\Types\Timestamp::class, $gk->updated);
        $this->assertEquals(1234567890123, (int)($gk->updated->getValue()*1000));
    }

    /**
     * @covers \FeideConnect\Data\Repositories\Cassandra2::getClient
     */
    public function testGetClient() {
        $id = Models\Client::genUUID();
        $owner_uuid = \FeideConnect\Data\Model::genUUID();

        $this->db->rawExecute('INSERT INTO "clients" ("id", "client_secret", "created", "descr", "name", "owner", "organization", "authproviders", "logo", "redirect_uri", "scopes", "scopes_requested", "status", "type", "updated", "orgauthorization", "authoptions", "supporturl", "privacypolicyurl", "homepageurl") VALUES(:id, :client_secret, :created, :descr, :name, :owner, :organization, :authproviders, :logo, :redirect_uri, :scopes, :scopes_requested, :status, :type, :updated, :orgauthorization, :authoptions, :supporturl, :privacypolicyurl, :homepageurl)', [
            'id' => new \Cassandra\Uuid($id),
            'client_secret' => 'secret',
            'created' => new \Cassandra\Timestamp(1122334455, 667000),
            'descr' => 'Client description',
            'name' => 'Client name',
            'owner' => new \Cassandra\Uuid($owner_uuid),
            'organization' => 'Foo Inc.',
            'authproviders' => \Cassandra\Type::set(\Cassandra\Type::text())->create('some-provider'),
            'logo' => 'client-logo',
            'redirect_uri' => \Cassandra\Type::collection(\Cassandra\Type::text())->create('https://foo.example.org/redirect'),
            'scopes' => \Cassandra\Type::set(\Cassandra\Type::text())->create('foo-scope'),
            'scopes_requested' => \Cassandra\Type::set(\Cassandra\Type::text())->create('foo-scope-requested'),
            'status' => \Cassandra\Type::set(\Cassandra\Type::text())->create('some-status'),
            'type' => 'type-value',
            'updated' => new \Cassandra\Timestamp(1234567890, 123000),
            'orgauthorization' => \Cassandra\Type::map(\Cassandra\Type::text(), \Cassandra\Type::text())->create(
                'example.org', json_encode(['orgauthorization-value'])
            ),
            'authoptions' => json_encode(['authoptions-key' => 'authoptions-value']),
            'supporturl' => 'https://foo.example.org/support',
            'privacypolicyurl' => 'https://foo.example.org/privacy-policy',
            'homepageurl' => 'https://foo.example.org/',
        ]);

        $client = $this->db->getClient($id);
        $this->assertNotNull($client);
        $this->assertEquals('secret', $client->client_secret);
        $this->assertInstanceOf(\FeideConnect\Data\Types\Timestamp::class, $client->created);
        $this->assertEquals(1122334455667, (int)($client->created->getValue()*1000));
        $this->assertEquals('Client description', $client->descr);
        $this->assertEquals('Client name', $client->name);
        $this->assertEquals($owner_uuid, $client->owner);
        $this->assertEquals('Foo Inc.', $client->organization);
        $this->assertEquals('client-logo', $client->logo);
        $this->assertEquals(['https://foo.example.org/redirect'], $client->redirect_uri);
        $this->assertEquals(['foo-scope'], $client->scopes);
        $this->assertEquals(['foo-scope-requested'], $client->scopes_requested);
        $this->assertEquals(['some-status'], $client->status);
        $this->assertEquals('type-value', $client->type);
        $this->assertInstanceOf(\FeideConnect\Data\Types\Timestamp::class, $client->updated);
        $this->assertEquals(1234567890123, (int)($client->updated->getValue()*1000));
        $this->assertEquals(['some-provider'], $client->authproviders);
        $this->assertArrayHasKey('example.org', $client->orgauthorization);
        $this->assertEquals(['orgauthorization-value'], $client->orgauthorization['example.org']);
        $this->assertArrayHasKey('authoptions-key', $client->authoptions);
        $this->assertEquals('authoptions-value', $client->authoptions['authoptions-key']);

        // TODO: These fields do not actually exist in the Client object -- they are just fetched.
        // Nevertheless, storing them to the object works, since this is PHP.
        $this->assertEquals('https://foo.example.org/support', $client->supporturl);
        $this->assertEquals('https://foo.example.org/privacy-policy', $client->privacypolicyurl);
        $this->assertEquals('https://foo.example.org/', $client->homepageurl);
    }

    /**
     * @covers \FeideConnect\Data\Repositories\Cassandra2::checkMandatory
     */
    public function testCheckMandatory() {
        $clientid = Models\Client::genUUID();

        $this->db->rawExecute('INSERT INTO "clients" ("id") VALUES(:id)', [
            'id' => new \Cassandra\Uuid($clientid),
        ]);
        $this->db->rawExecute('INSERT INTO "mandatory_clients" ("realm", "clientid") VALUES(:realm, :clientid)', [
            'realm' => 'example.org',
            'clientid' => new \Cassandra\Uuid($clientid),
        ]);

        $client = $this->db->getClient($clientid);
        $result = $this->db->checkMandatory('example.org', $client);
        $this->assertNotNull($result);

        $result = $this->db->checkMandatory('not.example.org', $client);
        $this->assertNull($result);
    }

    /**
     * @covers \FeideConnect\Data\Repositories\Cassandra2::getOrgsByService
     */
    public function testGetOrgsByService() {
        $orgid_a = bin2hex(openssl_random_pseudo_bytes(8));
        $orgid_b = bin2hex(openssl_random_pseudo_bytes(8));

        $this->db->rawExecute('TRUNCATE organizations', []);
        $this->db->rawExecute('INSERT INTO "organizations" ("id", "services") VALUES(:id, :services)', [
            'id' => $orgid_a,
            'services' => \Cassandra\Type::set(\Cassandra\Type::text())->create('service-1'),
        ]);
        $this->db->rawExecute('INSERT INTO "organizations" ("id", "services") VALUES(:id, :services)', [
            'id' => $orgid_b,
            'services' => \Cassandra\Type::set(\Cassandra\Type::text())->create('service-1', 'service-2'),
        ]);

        $result = $this->db->getOrgsByService('service-1');
        $this->assertCount(2, $result);
        $this->assertTrue($result[0]->id === $orgid_a || $result[1]->id === $orgid_a);
        $this->assertTrue($result[0]->id === $orgid_b || $result[1]->id === $orgid_b);

        $result = $this->db->getOrgsByService('service-2');
        $this->assertCount(1, $result);
        $this->assertEquals($orgid_b, $result[0]->id);

        $result = $this->db->getOrgsByService('service-wrong');
        $this->assertCount(0, $result);
    }

    /**
     * @covers \FeideConnect\Data\Repositories\Cassandra2::getOrg
     */
    public function testGetOrg() {
        $orgid = bin2hex(openssl_random_pseudo_bytes(8));

        $this->db->rawExecute('INSERT INTO "organizations" ("id", "name", "realm", "type", "uiinfo", "services") VALUES(:id, :name, :realm, :type, :uiinfo, :services)', [
            'id' => $orgid,
            'name' => \Cassandra\Type::map(\Cassandra\Type::text(), \Cassandra\Type::text())->create(
                'nb', 'Organisasjonsnavn',
                'en', 'Organization name'
            ),
            'realm' => 'example.org',
            'type' => \Cassandra\Type::set(\Cassandra\Type::text())->create('type-value'),
            'uiinfo' => json_encode(['uiinfo-key' => 'uiinfo-value']),
            'services' => \Cassandra\Type::set(\Cassandra\Type::text())->create('service-value'),
        ]);

        $org = $this->db->getOrg($orgid);
        $this->assertNotNull($org);
        $this->assertEquals($orgid, $org->id);
        $this->assertArrayHasKey('nb', $org->name);
        $this->assertEquals('Organisasjonsnavn', $org->name['nb']);
        $this->assertArrayHasKey('en', $org->name);
        $this->assertEquals('Organization name', $org->name['en']);
        $this->assertEquals('example.org', $org->realm);
        $this->assertEquals(['type-value'], $org->type);
        $this->assertArrayHasKey('uiinfo-key', $org->uiinfo);
        $this->assertEquals('uiinfo-value', $org->uiinfo['uiinfo-key']);
        $this->assertEquals(['service-value'], $org->services);
    }

    /**
     * @covers \FeideConnect\Data\Repositories\Cassandra2::getAccessToken
     */
    public function testGetAccessToken() {
        $id = Models\AccessToken::genUUID();
        $clientid = Models\Client::genUUID();
        $userid = Models\User::genUUID();
        $subtoken_id = Models\AccessToken::genUUID();

        $this->db->rawExecute('INSERT INTO "oauth_tokens" ("access_token", "clientid", "userid", "issued", "scope", "token_type", "validuntil", "lastuse", "apigkid", "subtokens") VALUES(:access_token, :clientid, :userid, :issued, :scope, :token_type, :validuntil, :lastuse, :apigkid, :subtokens)', [
            'access_token' => new \Cassandra\Uuid($id),
            'clientid' => new \Cassandra\Uuid($clientid),
            'userid' => new \Cassandra\Uuid($userid),
            'issued' => new \Cassandra\Timestamp(1000000000, 0),
            'scope' => \Cassandra\Type::set(\Cassandra\Type::text())->create('scope-value'),
            'token_type' => 'token-type-value',
            'validuntil' => new \Cassandra\Timestamp(1234567890, 123000),
            'lastuse' => new \Cassandra\Timestamp(1122334455, 667000),
            'apigkid' => 'apigkid-value',
            'subtokens' => \Cassandra\Type::map(\Cassandra\Type::text(), \Cassandra\Type::uuid())->create(
                'foo', new \Cassandra\Uuid($subtoken_id)
            ),
        ]);

        $token = $this->db->getAccessToken($id);
        $this->assertNotNull($token);
        $this->assertEquals($id, $token->access_token);
        $this->assertEquals($clientid, $token->clientid);
        $this->assertEquals($userid, $token->userid);
        $this->assertInstanceOf(\FeideConnect\Data\Types\Timestamp::class, $token->issued);
        $this->assertEquals(1000000000000, (int)($token->issued->getValue()*1000));
        $this->assertEquals(['scope-value'], $token->scope);
        $this->assertEquals('token-type-value', $token->token_type);
        $this->assertInstanceOf(\FeideConnect\Data\Types\Timestamp::class, $token->validuntil);
        $this->assertEquals(1234567890123, (int)($token->validuntil->getValue()*1000));
        $this->assertInstanceOf(\FeideConnect\Data\Types\Timestamp::class, $token->lastuse);
        $this->assertEquals(1122334455667, (int)($token->lastuse->getValue()*1000));
        $this->assertEquals('apigkid-value', $token->apigkid);
        $this->assertArrayHasKey('foo', $token->subtokens);
        $this->assertEquals($subtoken_id, $token->subtokens['foo']);

        $token = $this->db->getAccessToken(Models\AccessToken::genUUID());
        $this->assertNull($token);
    }

    /**
     * @covers \FeideConnect\Data\Repositories\Cassandra2::getAccessToken
     */
    public function testGetAccessTokens() {
        $id_a = Models\AccessToken::genUUID();
        $id_b = Models\AccessToken::genUUID();
        $clientid = Models\Client::genUUID();
        $userid = Models\User::genUUID();

        $this->db->rawExecute('INSERT INTO "oauth_tokens" ("access_token", "clientid", "userid", "apigkid") VALUES(:access_token, :clientid, :userid, :apigkid)', [
            'access_token' => new \Cassandra\Uuid($id_a),
            'clientid' => new \Cassandra\Uuid($clientid),
            'userid' => new \Cassandra\Uuid($userid),
            'apigkid' => '',
        ]);

        $this->db->rawExecute('INSERT INTO "oauth_tokens" ("access_token", "clientid", "userid", "apigkid") VALUES(:access_token, :clientid, :userid, :apigkid)', [
            'access_token' => new \Cassandra\Uuid($id_b),
            'clientid' => new \Cassandra\Uuid($clientid),
            'userid' => new \Cassandra\Uuid($userid),
            'apigkid' => '',
        ]);

        $tokens = $this->db->getAccessTokens($userid, $clientid);
        $this->assertCount(2, $tokens);
        $this->assertTrue($tokens[0]->access_token === $id_a || $tokens[1]->access_token === $id_a);
        $this->assertTrue($tokens[0]->access_token === $id_b || $tokens[1]->access_token === $id_b);

        $tokens = $this->db->getAccessTokens($userid, Models\Client::genUUID());
        $this->assertCount(0, $tokens);

        $tokens = $this->db->getAccessTokens(Models\User::genUUID(), $clientid);
        $this->assertCount(0, $tokens);
    }

    /**
     * @covers \FeideConnect\Data\Repositories\Cassandra2::saveToken
     */
    public function testSaveToken() {
        $id = Models\AccessToken::genUUID();
        $clientid = Models\Client::genUUID();
        $userid = Models\User::genUUID();
        $subtoken_id = Models\AccessToken::genUUID();
        $validuntil = time() + 600;
        $token = new Models\AccessToken();
        $token->clientid = $clientid;
        $token->userid = $userid;
        $token->apigkid = 'apigkid-value';
        $token->issued = new \FeideConnect\Data\Types\Timestamp(1000000000.000);
        $token->validuntil = new \FeideConnect\Data\Types\Timestamp($validuntil);
        $token->access_token = $id;
        $token->token_type = 'token-type-value';
        $token->scope = [ 'scope-value' ];
        $this->db->saveToken($token);

        $results = $this->db->rawQuery('SELECT * FROM "oauth_tokens" WHERE access_token = :id', [
            'id' => new \Cassandra\Uuid($id),
        ]);
        $this->assertCount(1, $results);
        $storedToken = $results[0];
        $this->assertEquals($clientid, $storedToken['clientid']);
        $this->assertEquals($userid, $storedToken['userid']);
        $this->assertEquals('apigkid-value', $storedToken['apigkid']);
        $this->assertEquals(1000000000.000, $storedToken['issued']->microtime(true));
        $this->assertEquals($validuntil, $storedToken['validuntil']->microtime(true));
        $this->assertEquals($id, $storedToken['access_token']);
        $this->assertEquals('token-type-value', $storedToken['token_type']);
        $this->assertEquals(['scope-value'], $storedToken['scope']->values());

        $results = $this->db->rawQuery('SELECT count_tokens FROM "clients_counters" WHERE id = :id', [
            'id' => new \Cassandra\Uuid($clientid),
        ]);
        $this->assertCount(1, $results);
        $counters = $results[0];
        $this->assertEquals(1, $counters['count_tokens']->value());
    }

    /**
     * @covers \FeideConnect\Data\Repositories\Cassandra2::getAuthorization
     */
    public function testGetAuthorization() {
        $clientid = Models\Client::genUUID();
        $userid = Models\User::genUUID();

        $this->db->rawExecute('INSERT INTO "oauth_authorizations" ("clientid", "userid", "scopes", "issued", "apigk_scopes") VALUES(:clientid, :userid, :scopes, :issued, :apigk_scopes)', [
            'clientid' => new \Cassandra\Uuid($clientid),
            'userid' => new \Cassandra\Uuid($userid),
            'scopes' => \Cassandra\Type::set(\Cassandra\Type::text())->create('scopes-value'),
            'issued' => new \Cassandra\Timestamp(1000000000, 0),
            'apigk_scopes' => \Cassandra\Type::map(\Cassandra\Type::text(), \Cassandra\Type::set(\Cassandra\Type::text()))->create(
                'foo', \Cassandra\Type::set(\Cassandra\Type::text())->create('foo-value')
            ),
        ]);

        $auth = $this->db->getAuthorization($userid, $clientid);
        $this->assertNotNull($auth);
        $this->assertEquals($clientid, $auth->clientid);
        $this->assertEquals($userid, $auth->userid);
        $this->assertEquals(['scopes-value'], $auth->scopes);
        $this->assertInstanceOf(\FeideConnect\Data\Types\Timestamp::class, $auth->issued);
        $this->assertEquals(1000000000000, (int)($auth->issued->getValue()*1000));
        $this->assertArrayHasKey('foo', $auth->apigk_scopes);
        $this->assertEquals(['foo-value'], $auth->apigk_scopes['foo']);

        $auth = $this->db->getAccessToken($userid, Models\Client::genUUID());
        $this->assertNull($auth);
        $auth = $this->db->getAccessToken(Models\User::genUUID(), $clientid);
        $this->assertNull($auth);
    }

    /**
     * @covers \FeideConnect\Data\Repositories\Cassandra2::saveAuthorization
     */
    public function testSaveAuthorization() {
        $id = Models\AccessToken::genUUID();
        $clientid = Models\Client::genUUID();
        $userid = Models\User::genUUID();
        $subtoken_id = Models\AccessToken::genUUID();

        $auth = new Models\Authorization([
            'clientid' => $clientid,
            'userid' => $userid,
            'issued' => new \FeideConnect\Data\Types\Timestamp(1000000000),
            'scopes' => ['scopes-value'],
            'apigk_scopes' => [
                'foo' => [ 'foo-value' ],
            ],
        ]);
        $this->db->saveAuthorization($auth);

        $results = $this->db->rawQuery('SELECT * FROM "oauth_authorizations" WHERE "userid" = :userid AND "clientid" = :clientid', [
            'userid' => new \Cassandra\Uuid($userid),
            'clientid' => new \Cassandra\Uuid($clientid),
        ]);
        $this->assertCount(1, $results);
        $storedAuth = $results[0];
        $this->assertEquals($clientid, $storedAuth['clientid']);
        $this->assertEquals($userid, $storedAuth['userid']);
        $this->assertEquals(1000000000, $storedAuth['issued']->microtime(true));
        $this->assertEquals(['scopes-value'], $storedAuth['scopes']->values());
        $this->assertArrayHasKey('foo', $storedAuth['apigk_scopes']);
        $this->assertEquals(['foo-value'], $storedAuth['apigk_scopes']['foo']->values());

        $results = $this->db->rawQuery('SELECT count_users FROM "clients_counters" WHERE id = :id', [
            'id' => new \Cassandra\Uuid($clientid),
        ]);
        $this->assertCount(1, $results);
        $counters = $results[0];
        $this->assertEquals(1, $counters['count_users']->value());
    }

    /**
     * @covers \FeideConnect\Data\Repositories\Cassandra2::getAuthorizationCode
     */
    public function testGetAuthorizationCode() {
        $code = Models\AuthorizationCode::genUUID();
        $clientid = Models\Client::genUUID();
        $userid = Models\User::genUUID();

        $this->db->rawExecute('INSERT INTO "oauth_codes" ("code", "clientid", "userid", "scope", "token_type", "redirect_uri", "idtoken", "issued", "validuntil", "apigk_scopes") VALUES(:code, :clientid, :userid, :scope, :token_type, :redirect_uri, :idtoken, :issued, :validuntil, :apigk_scopes)', [
            'code' => new \Cassandra\Uuid($code),
            'clientid' => new \Cassandra\Uuid($clientid),
            'userid' => new \Cassandra\Uuid($userid),
            'scope' => \Cassandra\Type::set(\Cassandra\Type::text())->create('scope-value'),
            'token_type' => 'token-type-value',
            'redirect_uri' => 'https://foo.example.org/redirect',
            'idtoken' => 'idtoken-value',
            'issued' => new \Cassandra\Timestamp(1000000000, 0),
            'validuntil' => new \Cassandra\Timestamp(1234567890, 123000),
            'apigk_scopes' => \Cassandra\Type::map(\Cassandra\Type::text(), \Cassandra\Type::set(\Cassandra\Type::text()))->create(
                'foo', \Cassandra\Type::set(\Cassandra\Type::text())->create('foo-value')
            ),
        ]);

        $authcode = $this->db->getAuthorizationCode($code);
        $this->assertNotNull($authcode);
        $this->assertEquals($code, $authcode->code);
        $this->assertEquals($clientid, $authcode->clientid);
        $this->assertEquals($userid, $authcode->userid);
        $this->assertEquals(['scope-value'], $authcode->scope);
        $this->assertEquals('token-type-value', $authcode->token_type);
        $this->assertEquals('https://foo.example.org/redirect', $authcode->redirect_uri);
        $this->assertEquals('idtoken-value', $authcode->idtoken);
        $this->assertInstanceOf(\FeideConnect\Data\Types\Timestamp::class, $authcode->issued);
        $this->assertEquals(1000000000000, (int)($authcode->issued->getValue()*1000));
        $this->assertInstanceOf(\FeideConnect\Data\Types\Timestamp::class, $authcode->validuntil);
        $this->assertEquals(1234567890123, (int)($authcode->validuntil->getValue()*1000));
        $this->assertArrayHasKey('foo', $authcode->apigk_scopes);
        $this->assertEquals(['foo-value'], $authcode->apigk_scopes['foo']);

        $authcode = $this->db->getAuthorizationCode(Models\AuthorizationCode::genUUID());
        $this->assertNull($authcode);
    }

    /**
     * @covers \FeideConnect\Data\Repositories\Cassandra2::saveAuthorizationCode
     */
    public function testSaveAuthorizationCode() {
        $code = Models\AuthorizationCode::genUUID();
        $clientid = Models\Client::genUUID();
        $userid = Models\User::genUUID();
        $subtoken_id = Models\AccessToken::genUUID();
        $validuntil = time() + 600;

        $authcode = new Models\AuthorizationCode();
        $authcode->code = $code;
        $authcode->clientid = $clientid;
        $authcode->userid = $userid;
        $authcode->issued = new \FeideConnect\Data\Types\Timestamp(1000000000.000);
        $authcode->validuntil = new \FeideConnect\Data\Types\Timestamp($validuntil);
        $authcode->token_type = 'token-type-value';
        $authcode->idtoken = 'idtoken-value';
        $authcode->redirect_uri = 'https://foo.example.org/redirect';
        $authcode->scope = [ 'scope-value' ];
        $authcode->apigk_scopes = [
            'foo' => [ 'foo-value' ],
        ];
        $this->db->saveAuthorizationCode($authcode);

        $results = $this->db->rawQuery('SELECT * FROM "oauth_codes" WHERE code = :code', [
            'code' => new \Cassandra\Uuid($code),
        ]);
        $this->assertCount(1, $results);
        $storedCode = $results[0];
        $this->assertEquals($code, $storedCode['code']);
        $this->assertEquals($clientid, $storedCode['clientid']);
        $this->assertEquals($userid, $storedCode['userid']);
        $this->assertEquals(1000000000.000, $storedCode['issued']->microtime(true));
        $this->assertEquals($validuntil, $storedCode['validuntil']->microtime(true));
        $this->assertEquals('token-type-value', $storedCode['token_type']);
        $this->assertEquals('idtoken-value', $storedCode['idtoken']);
        $this->assertEquals('https://foo.example.org/redirect', $storedCode['redirect_uri']);
        $this->assertEquals(['scope-value'], $storedCode['scope']->values());
        $this->assertArrayHasKey('foo', $storedCode['apigk_scopes']);
        $this->assertEquals(['foo-value'], $storedCode['apigk_scopes']['foo']->values());
    }

    /**
     * @covers \FeideConnect\Data\Repositories\Cassandra2::removeAuthorizationCode
     */
    public function testRemoveAuthorizationCode() {
        $code = Models\AuthorizationCode::genUUID();

        $this->db->rawExecute('INSERT INTO "oauth_codes" ("code") VALUES(:code)', [
            'code' => new \Cassandra\Uuid($code),
        ]);

        $authcode = $this->db->getAuthorizationCode($code);
        $this->db->removeAuthorizationCode($authcode);

        $results = $this->db->rawQuery('SELECT code FROM "oauth_codes" WHERE code = :code', [
            'code' => new \Cassandra\Uuid($code),
        ]);
        $this->assertCount(0, $results);
    }

    /**
     * @covers \FeideConnect\Data\Repositories\Cassandra2::updateLoginStats
     */
    public function testUpdateLoginStats() {

        // We wrap the insertion code in a loop to ensure that we can tell the exact time the data
        // was stored in.
        do {
            $start = new \FeideConnect\Data\Types\Timestamp();
            $start->roundseconds(60);

            // We generate a new client id for each loop, so that we can ensure that the counter is zero.
            $clientid = Models\Client::genUUID();
            $this->db->rawExecute('INSERT INTO "clients" ("id") VALUES(:id)', [
                'id' => new \Cassandra\Uuid($clientid),
            ]);
            $client = $this->db->getClient($clientid);

            $this->db->updateLoginStats($client, 'foo-auth');

            $end = new \FeideConnect\Data\Types\Timestamp();
            $end->roundseconds(60);
            // Loop if the time slot has changed. If it is different we can't tell
            // what time slot updateLoginStats() has updated.
        } while ($start->getValue() !== $end->getValue());

        $results = $this->db->rawQuery('SELECT login_count FROM logins_stats WHERE clientid = :clientid AND date = :date AND authsource = :authsource AND timeslot = :timeslot', [
            'clientid' => new \Cassandra\Uuid($clientid),
            'date' => $start->datestring(),
            'authsource' => 'foo-auth',
            'timeslot' => $start->getCassandraTimestamp(),
        ]);
        $this->assertCount(1, $results);
        $result = $results[0];
        $this->assertEquals(1, $result['login_count']->value());
    }

}
