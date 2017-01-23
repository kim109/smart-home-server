<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Sunra\PhpSimple\HtmlDomParser;
use GuzzleHttp\Exception\RequestException;

class BoilerController extends Controller
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

        $response = $client->get('/WebHomeNetwork/DeviceControl/HeatingContainer.aspx');
        $body = $response->getBody();
        $html = HtmlDomParser::str_get_html($body);

        $result = array();
        for ($i=0; $html->find('#_ctl'.$i.'_ImgStatus', 0) != null; $i++) {
            $label = $html->find('#_ctl'.$i.'_lblLocation', 0)->innertext;

            $response = $client->get('/WebHomeNetwork/DeviceControl/heating_control.aspx?UID=01241'.($i+1));
            $body = $response->getBody();
            $detail = HtmlDomParser::str_get_html($body);

            $status = $detail->find('#cboSetOnOff option[selected=selected]', 0)->value;
            $set = 0;
            if ($status == 'on') {
                $set = (int)$detail->find('#cboSetTemp option[selected=selected]', 0)->value;
            }
            $currnet = iconv('EUC-KR', 'UTF-8', $detail->find('font[color=red]', 1)->innertext);

            $result[$i] = [
                'label' => trim(iconv('EUC-KR', 'UTF-8', $label)),
                'status' => ($status=='on'),
                'set' => $set,
                'currnet' => $currnet
            ];
        }

        return response()->json($result);
    }


    public function set($user, $id, Request $request)
    {
        if (preg_match('/^[0-9]+$/', $id) !== 1) {
            return response()->json(['errors'=>'invalid url'], 404);
        }

        $range = range(15, 32);
        $range[] = 0;
        $temperature = $request->input('temperature');
        if (!in_array($temperature, $range)) {
            return response()->json(['errors'=>'invalid temperature value'], 422);
        }
        $method = ($temperature == 0) ? 'off' : 'on';

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

        $url = '/WebHomeNetwork/DeviceControl/heating_control.aspx?UID=01241'.($id+1);
        $response = $client->get($url);
        $body = $response->getBody();
        $html = HtmlDomParser::str_get_html($body);

        $view = $html->find('#__VIEWSTATE', 0)->value;
        $event = $html->find('#__EVENTVALIDATION', 0)->value;

        if (is_null($view) || is_null($event)) {
            return response()->json(['errors'=>'params error'], 500);
        }

        $params = [
            '__EVENTARGUMENT' => '',
            '__LASTFOCUS' => '',
            '__VIEWSTATE' => $view,
            '__EVENTVALIDATION' => $event,
            '__EVENTTARGET' => 'cboSetOnOff',
            'cboSetOnOff' => $method
        ];
        if ($method == 'on') {
            $params['cboSetTemp'] = $temperature;
        }

        $response = $client->post($url, ['form_params' => $params]);
        if ($response->getStatusCode() != 200) {
            return response()->json(['errors'=>'submit fail'], 500);
        }

        return response()->json(['success' => true]);
    }
}
