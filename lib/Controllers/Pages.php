<?php


namespace FeideConnect\Controllers;

use FeideConnect\HTTP\HTTPResponse;
use FeideConnect\HTTP\TextResponse;
use FeideConnect\HTTP\JSONResponse;
use FeideConnect\HTTP\TemplatedHTMLResponse;
use FeideConnect\Config;
use FeideConnect\Utils\Misc;
use FeideConnect\Utils\URL;

class Pages {

    static function reject() {

        return (new TemplatedHTMLResponse('reject'))->setData([
            "head" => "You rejected the authorization request for an application"
        ]);
    }

    static function loggedout() {

        return (new TemplatedHTMLResponse('loggedout'))->setData([
            "head" => "You are now logged out"
        ]);
    }


    static function robot() {
        $txt = "User-agent: *\nDisallow: /\n";
        return new TextResponse($txt);
    }

    static function emptyResponse() {

        return (new TemplatedHTMLResponse('emptyresponsee'));

    }

    static function debug() {



        $data = [];
        $data['endpoints'] = [
            "oauth.base" => URL::getBaseURL() . 'oauth/',
            "this" => URL::selfURL(),
            "this.noquery" => URL::selfURLNoQuery(),
            "this.host" =>  URL::selfURLhost(),
        ];
        $data['client'] = $_SERVER['REMOTE_ADDR'];


        $cookie = (isset($_COOKIE['lang']) ? $_COOKIE['lang'] : null);

        $hdrs = getallheaders();
        $langheader = (isset($hdrs['Accept-Language']) ? $hdrs['Accept-Language'] : null);
        // var_dump($hdrs); exit;

        $availlang = Config::getValue('availableLanguages');
        $defaultlang = $availlang[count($availlang) - 1];
        $locenabled = Config::getValue('enableLocalization');

        $data['lang'] = [
            "cookie" => $cookie,
            "accept-language" => $langheader,
            "available" => $availlang,
            "default" => $defaultlang,
            "enabled" => $locenabled,
            "selected" => Misc::getBrowserLanguage($availlang)
        ];

        $baseDIR = dirname(dirname(__DIR__));

        $data['dir'] = ["base" => $baseDIR];

        $data["files"] = [
            "config.json" => file_exists($baseDIR . '/etc/config.json'),
        ];

        $res = new JSONResponse($data);
        $res->setCORS(false);
        return $res;

    }


}