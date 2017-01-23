<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\RequestException;

class LoginController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    private $client;
    private $jar;

    public function __construct()
    {
        $this->jar = new CookieJar();

        $this->client = new Client([
            'base_uri' => env('SITE'),
            'headers' => [
                'User-Agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Mozilla/5.0 (Windows NT 10.0; WOW64; Trident/7.0; rv:11.0) like Gecko'
            ],
            'cookies' => $this->jar
        ]);
    }

    public function login(Request $request)
    {
        $user = $request->input('user');
        $password = $request->input('password');

        $jar = new CookieJar();
        $key = sprintf('%s:Session', $user);

        $this->client->get('/');
        $response = $this->client->post('/index_iframe.aspx', [
            'form_params' => [
                'uid' => $user,
                'upwd' => $password
            ]
        ]);
        if ($response->getStatusCode() != 200) {
            return response()->json(['errors' => 'login failure'], 401);
        }
        $body = $response->getBody();
        if (strpos($body, 'blank.htm') !== false) {
            $s = strpos($body, 'alert(\'')+7;
                    $e = strpos($body, '\');');
            $errmsg = substr($body, $s, ($e-$s));
            $errmsg = iconv('EUC-KR', 'UTF-8', $errmsg);

            return response()->json(['errors' => $errmsg], 401);
        }

        $cookies = $this->jar->toArray();

        foreach ($cookies as $item) {
            if ($item['Name'] == 'ASP.NET_SessionId') {
                $session = $item['Value'];
            }
        }
        apcu_store($key, $session, 300);
    }
}
