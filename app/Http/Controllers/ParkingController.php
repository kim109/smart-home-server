<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Sunra\PhpSimple\HtmlDomParser;
use GuzzleHttp\Exception\RequestException;

class ParkingController extends Controller
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

    public function get($user, Request $request)
    {
        $session = $request->input('session');
        $client = $this->client($session);


        // 1 Page 로드
        $response = $client->get('/hwork/Parking.aspx', [
            'query' => [
                'nPage' => 1,
                'txtSDate' => date('Y-m-d', time()-86400*30),
                'selSTime' => 0,
                'selSMinute' => 0,
                'txtEDate' => date('Y-m-d', time()+86400),
                'selETime' => 0,
                'selEMinute' => 0
            ]
        ]);
        $body = $response->getBody();
        $html = HtmlDomParser::str_get_html($body);
        $totalPage = count($html->find('.gelist'));

        foreach ($html->find('td[align=center]') as $td) {
            $plate = trim(strip_tags($td->innertext));

            if (preg_match('/\d{2}\S+\d{4}$/', $plate) === 1) {
                $plate = iconv('EUC-KR', 'UTF-8', $plate);
                $datetime = $td->prev_sibling()->prev_sibling()->innertext;
                $result[] = ['plate'=> $plate, 'datetime'=>$datetime];
            }
        }

        for ($i=2; $i<=$totalPage; $i++) {
            $response = $client->get('/hwork/Parking.aspx', [
                'query' => [
                    'nPage' => $i,
                    'txtSDate' => date('Y-m-d', time()-86400*30),
                    'selSTime' => 0,
                    'selSMinute' => 0,
                    'txtEDate' => date('Y-m-d', time()+86400),
                    'selETime' => 0,
                    'selEMinute' => 0
                ]
            ]);
            $body = $response->getBody();
            $html = HtmlDomParser::str_get_html($body);

            foreach ($html->find('td[align=center]') as $td) {
                $plate = trim(strip_tags($td->innertext));

                if (preg_match('/\d{2}\S+\d{4}$/', $plate) === 1) {
                    $plate = iconv('EUC-KR', 'UTF-8', $plate);
                    $datetime = $td->prev_sibling()->prev_sibling()->innertext;
                    $result[] = ['plate'=> $plate, 'datetime'=>$datetime];
                }
            }
        }

        return response()->json($result);
    }
}
