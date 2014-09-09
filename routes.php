<?php
use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;

$namespace = 'Cysha\Modules\Taylor\Controllers';

require_once 'routes-admin.php';
require_once 'routes-api.php';
require_once 'routes-module.php';

require_once(app_path().'/modules/taylor/goutte.phar');
Route::get('cmd', function () {
    $text = 'dy2';

    // get long & lat of requested location from google
    $gAPI = sprintf('http://maps.googleapis.com/maps/api/geocode/json?address=%s&sensor=false', urlencode($text));
    $gAPI = with(new GuzzleHttp\Client())->get($gAPI);
    if ($gAPI->getStatusCode() != '200') {
        die('cant query server');
        return Message::privmsg($command->message->channel(), color('Error: Could not query the server.'));
    }

    $gAPI = json_decode($gAPI->getBody(true));
    if (!count($gAPI)) {
        die('Invalid Location');
        return Message::privmsg($command->message->channel(), color('Error: Location seems to be invalid, try again.'));
    }

    $longLat = $gAPI->results[0]->geometry->location;
    $longLat = (string)$longLat->lat.','.$longLat->lng;

    $location = $gAPI->results[0]->formatted_address;

    // now query forecast.io with that information
    $url = sprintf('https://api.forecast.io/forecast/%s/%s', \Config::get('taylor::api.forecastio'), $longLat);
    $forecast = with(new GuzzleHttp\Client())->get($url);
    if ($forecast->getStatusCode() != '200') {
        die('cant query server');
        return Message::privmsg($command->message->channel(), color('Error: Could not query the server.'));
    }

    $forecast = json_decode($forecast->getBody(true));
    if (!isset($forecast->currently)) {
        die('cant query server');
        return Message::privmsg($command->message->channel(), color('Error: Location seems to be invalid, try again.'));
    }

    $parts = [
        $location,
        sprintf('%s (%s)', $forecast->currently->summary, $forecast->hourly->summary),
        sprintf('Temp: %d °C | %d °F', round((($forecast->currently->temperature-32)*5)/9), round($forecast->currently->temperature)),
        sprintf('Humidity: %d%%', $forecast->currently->humidity*100),
        sprintf('Winds: %d mph', $forecast->currently->windSpeed),
    ];
    echo \Debug::dump($parts, '');die;
    $msg = Message::privmsg($command->message->channel(), color('['. implode(' | ', $parts) .']'));
    echo \Debug::dump($msg, '');
});
