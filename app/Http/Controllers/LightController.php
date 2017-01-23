<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Sunra\PhpSimple\HtmlDomParser;
use GuzzleHttp\Exception\RequestException;

class LightController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */

    public function get($user, Request $request)
    {
        $session = $request->input('session');
        $client = $this->client($session);

        $response = $client->get('/hwork/homenetwork.aspx', [
            'query' => [
                'url' => '../WebHomeNetwork/DeviceControl/controlmain.aspx',
                'iframeH' => 650,
                'sM1Idx' => 5
            ]
        ]);
        $body = $response->getBody();
        $html = HtmlDomParser::str_get_html($body);
        $iframe = str_replace('..', '', $html->find('#HomeNetWork_iFrame', 0)->src);
        if (is_null($iframe)) {
            return response()->json(['errors'=>'Parser error - step1'], 500);
        }
        $url = urldecode($iframe);

        $response = $client->get($url);
        $body = $response->getBody();

        $response = $client->get('/WebHomeNetwork/DeviceControl/lightContainer.aspx');
        $body = $response->getBody();
        $html = HtmlDomParser::str_get_html($body);

        $view = $html->find('#__VIEWSTATE', 0)->value;
        $event = $html->find('#__EVENTVALIDATION', 0)->value;

        if (is_null($view) || is_null($event)) {
            return response()->json(['errors'=>'Parser error - step2'], 500);
        }
        apcu_store($user.':Light', ['view'=>$view, 'event'=>$event], 300);

        $result = array();
        for ($i=0; $html->find('#_ctl'.$i.'_ImgOnOFF', 0) != null; $i++) {
            $label = $html->find('#_ctl'.$i.'_lblLocation', 0)->innertext;
            $light = $html->find('#_ctl'.$i.'_ImgOnOFF', 0)->src;

            $result[$i] = [
                'label' => trim(iconv('EUC-KR', 'UTF-8', $label)),
                'switch' => strpos($light, 'img_on') !== false
            ];
        }

        return response()->json($result);
    }

    public function set($user, $id, Request $request)
    {
        if (preg_match('/^[0-9]+$/', $id) !== 1) {
            return response()->json(['errors'=>'invalid url'], 404);
        }
        $method = $request->input('method');
        if (!in_array($method, ['on', 'off'])) {
            return response()->json(['errors'=>'invalid method value'], 422);
        }

        $session = $request->input('session');
        $client = $this->client($session);

        $info = apcu_fetch($user.':Light');
        if ($info == false) {
            $response = $client->get('/hwork/homenetwork.aspx', [
                'query' => [
                    'url' => '../WebHomeNetwork/DeviceControl/controlmain.aspx',
                    'iframeH' => 650,
                    'sM1Idx' => 5
                ]
            ]);
            $body = $response->getBody();
            $html = HtmlDomParser::str_get_html($body);
            $iframe = str_replace('..', '', $html->find('#HomeNetWork_iFrame', 0)->src);
            if (is_null($iframe)) {
                return response()->json(['errors'=>'Parser error - step1'], 500);
            }
            $url = urldecode($iframe);

            $response = $client->get($url);
            $body = $response->getBody();

            $response = $client->get('/WebHomeNetwork/DeviceControl/lightContainer.aspx');
            $body = $response->getBody();
            $html = HtmlDomParser::str_get_html($body);

            $view = $html->find('#__VIEWSTATE', 0)->value;
            $event = $html->find('#__EVENTVALIDATION', 0)->value;
        } else {
            $view = $info['view'];
            $event = $info['event'];
        }

        $params = [
            '__VIEWSTATE' => $view,
            '__EVENTVALIDATION' => $event
        ];

        $x = sprintf('_ctl%d:btn%s.x', $id, $method);
        $params[$x] = rand(5, 60);
        $y = sprintf('_ctl%d:btn%s.y', $id, $method);
        $params[$y] = rand(2, 20);

        $response = $client->post('/WebHomeNetwork/DeviceControl/lightContainer.aspx', [
            'form_params' => $params
        ]);
        if ($response->getStatusCode() != 200) {
            return response()->json(['errors'=>'submit fail'], 500);
        }

        return response()->json(['success' => true]);
    }
}
