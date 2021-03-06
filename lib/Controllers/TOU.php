<?php


namespace FeideConnect\Controllers;

use FeideConnect\Authentication;
use FeideConnect\HTTP\LocalizedTemplatedHTMLResponse;


class TOU {

    public static function showGeneric() {

        $data = [
            "auth" => false,
            "organization" => 'din organisasjon'
        ];
        return (new LocalizedTemplatedHTMLResponse('tou'))->setData($data);

    }

    public static function showAuthenticated() {

        $auth = new Authentication\Authenticator();
        $auth->requireAuthentication();
        $account = $auth->getAccount();

        $data = [
            "auth" => true,
            "userids" => $account->getUserIDs(),
            "sourceID" => $account->getSourceID(),
            "name" => $account->getName(),
            "mail" => $account->getMail(),
            "organization" => $account->getOrg()
        ];
        return (new LocalizedTemplatedHTMLResponse('tou'))->setData($data);
    }


}
