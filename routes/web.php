<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$app->get('/', function () use ($app) {
    return $app->version();
});

$app->post('/login', 'LoginController@login');

$app->group(['middleware' => 'session', 'prefix' => '{user}'], function () use ($app) {
    $app->get('lights', 'LightController@get');
    $app->post('lights/{id}', 'LightController@set');

    $app->get('boilers', 'BoilerController@get');
    $app->post('boilers/{id}', 'BoilerController@set');

    $app->get('parking', 'ParkingController@get');

    $app->get('meter', 'MeterController@get');
});
