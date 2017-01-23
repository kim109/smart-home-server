<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller as BaseController;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;

class Controller extends BaseController
{
    public function __construct()
    {
    }

    protected function client($session = null)
    {
        $jar = CookieJar::fromArray(['ASP.NET_SessionId'=>$session], '175.213.153.4');

        $client = new Client([
            'base_uri' => env('SITE'),
            'headers' => [
                'User-Agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Mozilla/5.0 (Windows NT 10.0; WOW64; Trident/7.0; rv:11.0) like Gecko'
            ],
            'cookies' => $jar
        ]);

        return $client;
    }
}
