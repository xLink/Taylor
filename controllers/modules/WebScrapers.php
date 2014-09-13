<?php
require_once(app_path().'/modules/taylor/goutte.phar');
use Cysha\Modules\Taylor\Helpers\Irc\Command as Command;
use Cysha\Modules\Taylor\Helpers\Irc\Message as Message;
use Cysha\Modules\Taylor\Helpers\Irc as Irc;
use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;

$trigger = \Config::get('taylor::bot.command_trigger', '>');

Command::register($trigger.'xkcd', function (Command $command) {
    $url = sprintf('http://c.xkcd.com/random/comic/');
    $crawler = with(new Goutte\Client())->request('GET', $url);
    if (($crawler instanceof Symfony\Component\DomCrawler\Crawler) === false) {
        return Message::privmsg($command->message->channel(), color('Error: Could not query the server.'));
    }

    $parts = [
        'img' => '#comic img',
    ];

    foreach ($parts as $key => $selector) {
        $parts[$key] = $crawler->filter($selector)->count() > 0
                        ? strip_whitespace($crawler->filter($selector)->first()->attr('src'))
                        : null;
    }

    $msgs[] = Message::privmsg($command->message->channel(), color($parts['img']));

    return $msgs;
});

Command::register($trigger.'weather', function (Command $command) {
    return run_cmd($command->message->channel(), '>w', $command->params);
});

Command::register($trigger.'w', function (Command $command) {
    $text = $command->text;

    // get long & lat of requested location from google
    $gAPI = sprintf('http://maps.googleapis.com/maps/api/geocode/json?address=%s&sensor=false', urlencode($text));
    $gAPI = with(new GuzzleHttp\Client())->get($gAPI);
    if ($gAPI->getStatusCode() != '200') {
        return Message::privmsg($command->message->channel(), color('Error: Could not query the server.'));
    }

    $gAPI = json_decode($gAPI->getBody(true));
    if (!count($gAPI)) {
        return Message::privmsg($command->message->channel(), color('Error: Location seems to be invalid, try again.'));
    }

    $longLat = $gAPI->results[0]->geometry->location;
    $longLat = (string)$longLat->lat.','.$longLat->lng;

    $location = $gAPI->results[0]->formatted_address;

    // now query forecast.io with that information
    $url = sprintf('https://api.forecast.io/forecast/%s/%s', \Config::get('taylor::api.forecastio'), $longLat);
    $forecast = with(new GuzzleHttp\Client())->get($url);
    if ($forecast->getStatusCode() != '200') {
        return Message::privmsg($command->message->channel(), color('Error: Could not query the server.'));
    }

    $forecast = json_decode($forecast->getBody(true));
    if (!isset($forecast->currently)) {
        return Message::privmsg($command->message->channel(), color('Error: Location seems to be invalid, try again.'));
    }

    $parts = [
        $location,
        sprintf('%s (%s)', $forecast->currently->summary, $forecast->hourly->summary),
        sprintf('Temp: %d °C | %d °F', round((($forecast->currently->temperature-32)*5)/9), round($forecast->currently->temperature)),
        sprintf('Humidity: %d%%', $forecast->currently->humidity*100),
        sprintf('Winds: %d mph', $forecast->currently->windSpeed),
    ];

    return Message::privmsg($command->message->channel(), color('['. implode(' | ', $parts) .']'));
});

/** @author infy **/
Command::register($trigger.'fml', function (Command $command) {
    if (!count($command->params) || substr($command->params[0], 0, 1) == '?') {
        return Message::privmsg($command->message->channel(), 'Usage: >fml');
    }

    // Do the request.
    $request = with(new Goutte\Client())->request('GET', 'http://www.fmylife.com/random');
    if (($request instanceof Symfony\Component\DomCrawler\Crawler) === false) {
        return Message::privmsg($command->message->channel(), color('Error: Could not query the server.'));
    }

    $data = [
        'body'  =>  'div.post.article > p',
        'id'    =>  'div.left_part > a.jTip',
        'cat'   =>  'a.liencat'
    ];
    foreach ($data as $k => $v) {
        $data[$k] = $request->filter($v)->first()->text();
    }

    $msgs = [];
    $msgs[] = Message::privmsg($command->message->channel(), sprintf('FML (%s @ %s):', $data['id'], $data['cat']));
    $msgs[] = Message::privmsg($command->message->channel(), $data['body']);

    return $msgs;
});

/** @author infy **/
Command::register($trigger.'isup', function (Command $command) {
    if (!count($command->params) || substr($command->params[0], 0, 1) == '?') {
        return Message::privmsg($command->message->channel(), 'Usage: <url>');
    }

    $url = $command->params[0];

    // If there is no http(s):// in front of the URI, append it.
    if (!preg_match('#^(https?:\/\/)#i', $url)) {
        $url = 'http://'.$url;
    }

    // Now filter var should filter properly for URIs such as bash.org or facebook.com
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return Message::privmsg($command->message->channel(), 'Invalid URI supplied.');
    }

    try {
        $client = new Goutte\Client();
        $guzzle = $client->getClient(); // Get the client
        $guzzle->setDefaultOption('verify', false); // Don't verify SSL certificates.
        $client->setClient($guzzle); // Tell Goutte to use the modified client.
        $request = $client->request('GET', $url); // Fire off the request.
    } catch (GuzzleHttp\Exception\RequestException $b) {
        return Message::privmsg(
            $command->message->channel(),
            color($url.' appears to be down from here!', 'red')
        );
    }

    return Message::privmsg(
        $command->message->channel(),
        color($url.' is online from here.', 'green')
    );
});

/** @author infy **/
Command::register($trigger.'curr', function (Command $command) {
    if (count($command->params) != 3 || substr($command->params[0], 0, 1) == '?') { // Needs 3 parameters.
        return Message::privmsg($command->message->channel(), 'Usage: <value> <currency_from> <currency_to>, e.g. 1 USD EUR');
    }

    $params = [
        'q'     => $command->params[0],
        'from'  => $command->params[1],
        'to'    => $command->params[2],
    ];
    // grab the api
    $url = sprintf('http://rate-exchange.appspot.com/currency?%s', http_build_query($params));

    // make sure we got something
    $request = with(new GuzzleHttp\Client())->get($url);
    if ($request->getStatusCode() != '200') {
        return Message::privmsg($command->message->channel(), color('Error: Could not query the server.'));
    }

    // decode the json
    $data = json_decode($request->getBody(true), true);
    if (!count($data)) {
        return Message::privmsg($command->message->channel(), color('Error: Could not query the server.'));
    }

    if (isset($data['err'])) {
        return Message::privmsg($command->message->channel(), color('Error: Could not process input. Usage: <value> <currency_from> <currency_to>'));
    }

    // and output
    return Message::privmsg($command->message->channel(), sprintf('%.2f %s = %.2f %s', $params['q'], $params['from'], $data['v'], strtoupper($data['to'])));
});

Command::register($trigger.'calc', function (Command $command) {
    if (!count($command->params) || substr($command->params[0], 0, 1) == '?') {
        return Message::privmsg($command->message->channel(), 'Usage: <calculation query>');
    }

    $url = 'http://api.wolframalpha.com/v2/query?' . http_build_query([
        'input' =>  $command->text,
        'appid' =>  \Config::get('taylor::api.wolframalpha')
    ]);

    // grab the request
    $request = with(new Goutte\Client())->request('GET', $url);
    if (($request instanceof Symfony\Component\DomCrawler\Crawler) === false) {
        return Message::privmsg($command->message->channel(), color('Error: Could not query the server.'));
    }

    // setup some sane defaults to check for
    $return = [];
    $results = [
        '0' => $request->filterXPath('//pod[@id="Result"]/subpod'),
        '1' => $request->filterXPath('//pod/subpod'),
    ];

    foreach ($results as $key => $result) {
        // if we get a InvalidArgumentException, this one failed, continue over it
        try {
            $text = $result->text();
        } catch (InvalidArgumentException $e) {
            continue;
        }

        // process it and pass results back to $return
        $text = strip_whitespace($text);
        if (strpos("\n", $text) !== false) {
            $return[] = Message::privmsg($command->message->channel(), color($text));
        } else {
            $lines = explode("\n", $text);
            foreach ($lines as $text) {
                $return[] = Message::privmsg($command->message->channel(), color($text));
            }
        }
    }

    if (!count($return)) {
        return Message::privmsg($command->message->channel(), 'Could not get result. Please try again.');
    } else {
        return $return[0];
    }
});
