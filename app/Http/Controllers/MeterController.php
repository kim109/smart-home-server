<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Sunra\PhpSimple\HtmlDomParser;
use GuzzleHttp\Exception\RequestException;

class MeterController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    private function calcuteElectric($usage)
    {
        define('STEP1', 78.3);
        define('STEP2', 147.3);
        define('STEP3', 215.6);

        if ($usage <= 200) {
            $charge = 730;
            $charge += $usage*STEP1;
        } elseif ($usage > 200 && $usage <= 400) {
            $charge = 1260;
            $charge += (STEP1*200) + STEP2*($usage-200);
        } elseif ($usage > 400) {
            $charge = 6060;
            $charge += (STEP1*200) + (STEP2*200) + STEP3*($usage-400);
        }

        $charge = floor($charge);
        $charge += round($charge*0.1) + floor($charge*0.0037)*10;

        return floor($charge*0.1)*10;
    }

    private function calcuteWater($usage)
    {
        if ($usage <= 20) {
            $charge = $usage*430;   //상수도
            $charge += $usage*280;  //하수도
        } elseif ($usage > 20 && $usage <= 40) {
            $charge = (430*20) + 680*($usage-20);   // 상수도
            $charge += (280*20) + 440*($usage-20);  // 하수도
        } elseif ($usage > 40) {
            $charge = (430*20) + (680*20) + 900*($usage-40);    // 상수도
            $charge += (280*20) + (440*20) + 640*($usage-40);   // 하수도
        }
        $charge += $usage*160;

        return $charge;
    }

    private function calcuteGas($usage)
    {
        define('COOKING', 516);     // 취사용 열량
        define('STEP1', 15.6008);   // 취사용 가격
        define('STEP2', 17.2236);   // 난방용 가격

        $calory = $usage * 42.9;

        $charge = 850;
        if ($calory <= COOKING) {
            $charge += $calory * STEP1;
        } elseif ($calory > COOKING) {
            $charge += COOKING * STEP1;
            $charge += ($calory-COOKING) * STEP2;
        }
        $charge = round($charge*1.1);

        return $charge;
    }

    public function get($user, Request $request)
    {
        $timestamp = $request->input('date');
        if (!is_numeric($timestamp)) {
            $timestamp = time();
        }

        $session = $request->input('session');
        $client = $this->client($session);

        $response = $client->get('/hwork/iframe_DayValue.aspx');
        $body = $response->getBody();
        $html = HtmlDomParser::str_get_html($body);

        $view = $html->find('#__VIEWSTATE', 0)->value;

        $response = $client->post('/hwork/iframe_DayValue.aspx', [
            'form_params' => [
                '__VIEWSTATE' => $view,
                'txtFDate' => date('Y-m-d', $timestamp),
                'x' => rand(10, 60),
                'y' => rand(4, 15)
            ]
        ]);
        $body = $response->getBody();
        $html = HtmlDomParser::str_get_html($body);
        // 전기
        $now_electricity = (int)$html->find('td[height=25]', 2)->innertext;
        $total['electricity'] = number_format($now_electricity);
        $today['electricity'] = number_format($html->find('td[height=25]', 4)->innertext);

        // 수도
        $now_water = (int)$html->find('td[height=25]', 7)->innertext;
        $total['water'] = number_format($now_water);
        $today['water'] = number_format($html->find('td[height=25]', 9)->innertext);

        // 가스
        $now_gas = (int)$html->find('td[height=25]', 17)->innertext;
        $total['gas'] = number_format($now_gas);
        $today['gas'] = number_format($html->find('td[height=25]', 19)->innertext);

        // 전기요금 계산
        if (date('d', $timestamp) > 15) {
            $base_date = date('Y-m-15', $timestamp);
            $baseline['electricity'] = date('n/16', $timestamp);
        } else {
            $base_date = date('Y-m-15', strtotime('-1 month', $timestamp));
            $baseline['electricity'] = date('n/16', strtotime('-1 month', $timestamp));
        }
        $response = $client->post('/hwork/iframe_DayValue.aspx', [
            'form_params' => [
                '__VIEWSTATE' => $view,
                'txtFDate' => $base_date,
                'x' => rand(10, 60),
                'y' => rand(4, 15)
            ]
        ]);
        $body = $response->getBody();
        $html = HtmlDomParser::str_get_html($body);
        $base_electricity = (int)$html->find('td[height=25]', 2)->innertext;

        $usage['electricity'] = ($now_electricity - $base_electricity);
        $charge['electricity'] = number_format($this->calcuteElectric($usage['electricity']));

        // 수도요금 계산
        if (date('d', $timestamp) > 13) {
            $base_date = date('Y-m-13', $timestamp);
            $baseline['water'] = date('n/14', $timestamp);
        } else {
            $base_date = date('Y-m-13', strtotime('-1 month', $timestamp));
            $baseline['water'] = date('n/14', strtotime('-1 month', $timestamp));
        }
        $response = $client->post('/hwork/iframe_DayValue.aspx', [
            'form_params' => [
                '__VIEWSTATE' => $view,
                'txtFDate' => $base_date,
                'x' => rand(10, 60),
                'y' => rand(4, 15)
            ]
        ]);
        $body = $response->getBody();
        $html = HtmlDomParser::str_get_html($body);
        $base_water = (int)$html->find('td[height=25]', 7)->innertext;

        $usage['water']= ($now_water - $base_water);
        $charge['water'] = number_format($this->calcuteWater($usage['water']));


        // 가스요금 계산
        if (date('d', $timestamp) > 5) {
            $base_date = date('Y-m-05', $timestamp);
            $baseline['gas'] = date('n/6', $timestamp);
        } else {
            $base_date = date('Y-m-05', strtotime('-1 month', $timestamp));
            $baseline['gas'] = date('n/6', strtotime('-1 month', $timestamp));
        }
        $response = $client->post('/hwork/iframe_DayValue.aspx', [
            'form_params' => [
                '__VIEWSTATE' => $view,
                'txtFDate' => $base_date,
                'x' => rand(10, 60),
                'y' => rand(4, 15)
            ]
        ]);
        $body = $response->getBody();
        $html = HtmlDomParser::str_get_html($body);
        $base_gas = (int)$html->find('td[height=25]', 17)->innertext;

        $usage['gas']= ($now_gas - $base_gas);
        $charge['gas'] = number_format($this->calcuteWater($usage['gas']));


        $result = [
            'date' => date('n/d', $timestamp),
            'total' => $total,
            'today' => $today,
            'usage' => $usage,
            'charge' => $charge,
            'baseline' => $baseline
        ];

        return response()->json($result);
    }
}
