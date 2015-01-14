<?php
require_once(app_path().'/modules/taylor/goutte.phar');
use Cysha\Modules\Taylor\Helpers\Irc\Command as Command;
use Cysha\Modules\Taylor\Helpers\Irc\Message as Message;
use Cysha\Modules\Taylor\Helpers\Irc as Irc;
use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;

$trigger = \Config::get('taylor::bot.command_trigger', '>');

Command::register($trigger.'xkcd', function (Command $command) {
    if (substr($command->params[0], 0, 1) == '?') {
        return Message::privmsg($command->message->channel(), 'Usage: <comic_id=random>');
    }
    $comic_id = $command->params[0];
    if ($comic_id == 0 || !ctype_digit((string)$comic_id)) {
        $url = 'http://c.xkcd.com/random/comic/';
    } else {
        $url = sprintf('http://xkcd.com/%d/', $comic_id);
    }

    $request = goutteRequest(goutteClient(), $url, 'get');
    if (($request instanceof Symfony\Component\DomCrawler\Crawler) === false) {
        return Message::privmsg($command->message->channel(), color('Error: Could not query the server.'));
    }

    $body = $request->html();
    if (strpos($body, '404 - Not Found') !== false) {
        return Message::privmsg($command->message->channel(), color('Comic Not Found.'));
    }

    $parts = [
        'img' => '#comic img',
    ];

    foreach ($parts as $key => $selector) {
        $parts[$key] = $request->filter($selector)->count() > 0
                        ? strip_whitespace($request->filter($selector)->first()->attr('src'))
                        : null;
    }

    $id = inBetween('Permanent link to this comic: http://xkcd.com/', '/', $body);

    $msgs[] = Message::privmsg($command->message->channel(), color('xkcd#'.$id.' - '.$parts['img']));

    return $msgs;
});

Command::register($trigger.'cyaness', function (Command $command) {
    if (substr($command->params[0], 0, 1) == '?') {
        return Message::privmsg($command->message->channel(), 'Usage: <comic_id=random>');
    }

    $comic_id = $command->params[0];
    if ($comic_id == 0 || !ctype_digit((string)$comic_id)) {
        $comic_id = 'random';
    }

    $url = sprintf('http://explosm.net/comics/%s/', $comic_id);
    $request = goutteRequest(goutteClient(), $url, 'get');
    if (($request instanceof Symfony\Component\DomCrawler\Crawler) === false) {
        return Message::privmsg($command->message->channel(), color('Error: Could not query the server.'));
    }

    $body = $request->html();
    if (strpos($body, 'Comic could not be found') !== false) {
        return Message::privmsg($command->message->channel(), color('Comic Not Found.'));
    }

    $parts = [
        'img' => '#maincontent div[align=center] div img',
    ];

    foreach ($parts as $key => $selector) {
        $parts[$key] = $request->filter($selector)->count() > 0
                        ? strip_whitespace($request->filter($selector)->first()->attr('src'))
                        : null;
    }

    $id = inBetween('Cyanide & Happiness #', ' - Explosm.net', $request->filter('title')->text());

    $msgs[] = Message::privmsg($command->message->channel(), color('Cyanide & Happiness#'.$id.' - '.$parts['img']));

    return $msgs;
});

Command::register($trigger.'weather', function (Command $command) {
    return run_cmd($command->message->channel(), '>w', $command->params);
});

Command::register($trigger.'w', function (Command $command) {
    if (substr($command->params[0], 0, 1) == '?') {
        return Message::privmsg($command->message->channel(), 'Usage: <location>');
    }

    $text = null;

    // make sure we have something to go off
    if (!strlen($command->text)) {

        $client = new GuzzleHttp\Client([
            'base_url' => 'https://www.darchoods.net/api/irc/',
            'defaults' => ['headers' => ['X-Auth-Token' => Config::get('taylor::api.darchoods')]],
            'timeout'  => 2,
        ]);

        try {
            $request = $client->post('user/view', ['body' => [
                'username' => $command->sender->nick
            ]]);
        } catch (\GuzzleHttp\Exception\ServerException $e) {
            return Message::privmsg($command->message->channel(), color('Error: IRC API appears to be down, Try again later.'));
        }

        if ($request->getStatusCode() != '200') {
            return Message::privmsg($command->message->channel(), color('Error: IRC API appears to be down, Try again later.'));
        }

        $user = $request->json();
        if (!count($user) || ($accountName = array_get($user, 'data.user.account', null)) === null) {
            return Message::privmsg($command->message->channel(), color('Error: Location seems to be invalid, try again.'));
        }

        $authModel = Config::get('auth.model');
        $objUser = with(new $authModel)->whereUsername($accountName)->get()->first();

        if ($objUser === null) {
            return Message::privmsg($command->message->channel(), color('Error: Location seems to be invalid, try again.'));
        }
        $text = $objUser->weather;
    }

    if ($text === null) {
        $text = $command->text;
    }

    // get long & lat of requested location from google
    $gAPI = sprintf('http://maps.googleapis.com/maps/api/geocode/json?address=%s&sensor=false', urlencode($text));
    $gAPI = guzzleClient('get', $gAPI);
    if (($gAPI instanceof \GuzzleHttp\Message\Response) === false) {
        return Message::privmsg($command->message->channel(), color('Error: Could not query the server.'));
    }

    $gAPI = json_decode($gAPI->getBody(true));
    if (!count($gAPI) || !isset($gAPI->results[0])) {
        return Message::privmsg($command->message->channel(), color('Error: Location seems to be invalid, try again.'));
    }

    $longLat = $gAPI->results[0]->geometry->location;
    $longLat = (string)$longLat->lat.','.$longLat->lng;

    $location = $gAPI->results[0]->formatted_address;

    // now query forecast.io with that information
    $url = sprintf('https://api.forecast.io/forecast/%s/%s', \Config::get('taylor::api.forecastio'), $longLat);
    $forecast = guzzleClient('get', $url);
    if (($forecast instanceof \GuzzleHttp\Message\Response) === false) {
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
    // Do the request.
    $url = 'http://www.fmylife.com/random';
    $request = goutteRequest(goutteClient(), $url, 'get');
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
    if (empty($command->params[0]) || substr($command->params[0], 0, 1) == '?') {
        return Message::privmsg($command->message->channel(), 'Usage: <url>');
    }

    $url = $command->params[0];
    if (preg_match('(10\.(0|[1-9]|1[0-9]|2[0-4][0-9]|25[0-5]|1[0-9][0-9]?)\.(0|[1-9]|1[0-9]|2[0-4][0-9]|25[0-5]|1[0-9][0-9]?)\.(0|[1-9]|1[0-9]|2[0-4][0-9]|25[0-5]|1[0-9][0-9]?))', $url)) {
        return Message::privmsg($command->message->channel(), 'Invalid URI supplied.');
    }

    if (preg_match('(192\.168\.(0|[1-9]|1[0-9]|2[0-4][0-9]|25[0-5]|1[0-9][0-9]?)\.(0|[1-9]|1[0-9]|2[0-4][0-9]|25[0-5]|1[0-9][0-9]?))', $url)) {
        return Message::privmsg($command->message->channel(), 'Invalid URI supplied.');
    }

    if (preg_match('(172\.(1[6-9]|2[0-9]|3[0-1])\.(0|[1-9]|1[0-9]|2[0-4][0-9]|25[0-5]|1[0-9][0-9]?)\.(0|[1-9]|1[0-9]|2[0-4][0-9]|25[0-5]|1[0-9][0-9]?))', $url)) {
        return Message::privmsg($command->message->channel(), 'Invalid URI supplied.');
    }

    if (preg_match('(127.0.0.1)', $url)) {
        return Message::privmsg($command->message->channel(), 'Invalid URI supplied.');
    }

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
        $guzzle->setDefaultOption('config', ['curl' => ['CURLOPT_TIMEOUT' => 2]]); // timeout to 2 seconds
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
    if (empty($command->params[0]) || substr($command->params[0], 0, 1) == '?' || count($command->params) != 3) { // Needs 3 parameters.
        return Message::privmsg($command->message->channel(), 'Usage: <value> <currency_from> <currency_to>, e.g. 1 USD EUR');
    }

    $params = [
        'q'     => str_replace([','], '', $command->params[0]),
        'from'  => $command->params[1],
        'to'    => $command->params[2],
    ];
    // grab the api
    $url = sprintf('http://rate-exchange.appspot.com/currency?%s', http_build_query($params));

    // make sure we got something
    try {
        $request = with(new GuzzleHttp\Client())->get($url);
    } catch (\GuzzleHttp\Exception\ServerException $e) {
        return Message::privmsg($command->message->channel(), color('Error: Could not query the server.'));
    }

    if ($request->getStatusCode() != '200') {
        return Message::privmsg($command->message->channel(), color('Error: Could not query the server.'));
    }

    $data = json_decode($request->getBody(true), true);
    if (!count($data)) {
        return Message::privmsg($command->message->channel(), color('Error: Could not query the server.'));
    }

    if (isset($data['err'])) {
        return Message::privmsg($command->message->channel(), color('Error: Could not process input. Usage: <value> <currency_from> <currency_to>'));
    }

    if (isset($data['warning'])) {
        return Message::privmsg($command->message->channel(), color('Error: '.$data['warning']));
    }

    // and output
    return Message::privmsg($command->message->channel(), sprintf('%.2f %s = %.2f %s', $params['q'], $params['from'], $data['v'], strtoupper($data['to'])));
});

Command::register($trigger.'calc', function (Command $command) {
    if (empty($command->params[0]) || substr($command->params[0], 0, 1) == '?') {
        return Message::privmsg($command->message->channel(), 'Usage: <calculation query>');
    }

    $url = 'http://api.wolframalpha.com/v2/query?' . http_build_query([
        'input' =>  $command->text,
        'appid' =>  \Config::get('taylor::api.wolframalpha')
    ]);

    // grab the request
    $request = goutteRequest(goutteClient(), $url, 'get');
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
        } catch (ErrorException $e) {
            continue;
        } catch (Exception $e) {
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

Command::register($trigger.'yt', function (Command $command) {
    if (empty($command->params[0]) || substr($command->params[0], 0, 1) == '?') {
        return Message::privmsg($command->message->channel(), 'Usage: <search query>');
    }

    $url = 'https://www.googleapis.com/youtube/v3/search?' . http_build_query([
        'part'          => 'snippet',
        'q'             => $command->text,
        'key'           => Config::get('taylor::api.google.api-key')
    ]);

    // grab the request
    $request = guzzleClient('get', $url);
    if (!is_object($request)) {
        return Message::privmsg($command->message->channel(), 'Could not query server. Please try again.');
    }

    // make sure we got something
    $results = $request->json();
    if (array_get($results, 'kind') != 'youtube#searchListResponse') {
        return Message::privmsg($command->message->channel(), 'Could not get search results. Please try again.');
    }

    // make sure some results
    if (!count(array_get($results, 'items'))) {
        return Message::privmsg($command->message->channel(), 'No results found.');
    }

    // get the first result
    $first = array_get($results, 'items.0');

    // spawn a url
    $url = 'http://youtu.be/'.array_get($first, 'id.videoId');

    // trigger a url detection for that url
    $msgSet = [];
    Event::fire('taylor::privmsg: urlDetection', [$url, &$msgSet]);

    $msg = substr($msgSet['title'], 0, -1). '| '. $url.' ]';
    return Message::privmsg($command->message->channel(), color($msg));
});
