<?php
use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;

$namespace = 'Cysha\Modules\Taylor\Controllers';

require_once 'routes-admin.php';
require_once 'routes-api.php';
require_once 'routes-module.php';

require_once(app_path().'/modules/taylor/goutte.phar');


Route::get('cmd', function () {
    $urls = [];
    $urls[] = 'http://imgur.com/a/LctpK';
    $urls[] = 'http://imgur.com/gallery/LctpK';
    $urls[] = 'http://i.imgur.com/J866hUU.gif';
    $urls[] = 'http://imgur.com/gallery/18ILFXV';

    $raw = implode(' ', $urls);

    preg_match_all("@\b(https?://)?(([0-9a-zA-Z_!~*'().&=+$%-]+:)?[0-9a-zA-Z_!~*'().&=+$%-]+\@)?(([0-9]{1,3}\.){3}[0-9]{1,3}|([0-9a-zA-Z_!~*'()-]+\.)*([0-9a-zA-Z][0-9a-zA-Z-]{0,61})?[0-9a-zA-Z]\.[a-zA-Z]{2,6})(:[0-9]{1,4})?((/[0-9a-zA-Z_!~*'().;?:\@&=+$,%#-]+)*/?)@", $raw, $urls);

    if (!count($urls[0])) {
        return;
    }

    $msgs = [];
    foreach ($urls[0] as $url) {
        $msgSet = [];
        \Event::fire('taylor::privmsg: urlDetection', array($url, &$msgSet));

        if (!empty($msgSet) && count($msgSet)) {
            $msgs[$url] = $msgSet;
        } else {
            $msgs[$url] = null;
        }
    }

    echo \Debug::dump($msgs, '');
});


// setup a new client
$client = new GuzzleHttp\Client([
    'base_url' => 'http://dh.dev.daldridge.co.uk/api/qdb/',
    'timeout' => 2,
]);
Route::get('getQuote', function () use ($client) {
    #$channel = $command->message->channel();
    $channel = '#cybershade';

    $request = $client->post('search/byId', ['body' => [
        'channel' => $channel,
        'quote_id' => 1
    ]]);
    if ($request->getStatusCode() != '200') {
        die('nope...');
        return Message::privmsg($command->message->channel(), color('Error: Could not query the server.'));
    }

    $data = $request->json();
    echo \Debug::dump($data, '');

    $msg[] = sprintf('Quote#%s: %s', array_get($data, 'data.quote.quote_id', 0), array_get($data, 'data.quote.content'));
    echo \Debug::dump($msg, '');
});


// function getNode($request, $selector, $default = null)
// {
//     return $request->filter($selector)->count() ? strip_whitespace($request->filter($selector)->first()->text()) : $default;
// }


// function secs_to_h($secs)
// {
//     $units = array(
//         'year'   => 365*24*3600,
//         'month'  => 30*24*3600,
//         'week'   => 7*24*3600,
//         'day'    => 24*3600,
//         'hour'   => 3600,
//         'minute' => 60,
//         'second' => 1,
//     );

//     // specifically handle zero
//     if ($secs == 0) {
//         return '0 seconds';
//     }

//     $s = '';

//     foreach ($units as $name => $divisor) {
//         if ($quot = intval($secs / $divisor)) {
//             $s .= $quot.' '.$name;
//             $s .= (abs($quot) > 1 ? 's' : '') . ', ';
//             $secs -= $quot * $divisor;
//         }
//     }

//     return substr($s, 0, -2);
// }
