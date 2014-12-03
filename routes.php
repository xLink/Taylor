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
    //$urls[] = 'http://i.imgur.com/uYZva6M.jpg';
    //$urls[] = 'http://i.imgur.com/L1ceGMl.jpg';
    //$urls[] = 'http://i.imgur.com/086nf4F.gif';
    //$urls[] = 'http://i.imgur.com/88sqaZY.gif';
    //$urls[] = 'http://imgur.com/gallery/18ILFXV';
    $urls[] = 'http://i.imgur.com/5VmPTIE.gif';
    $urls[] = 'http://i.imgur.com/p6TECS5.jpg';
    $urls[] = 'https://i.imgur.com/3SR3yWA.gif';
    $urls[] = 'https://i.imgur.com/FY97Q1t.jpg';
    $urls[] = 'http://i.imgur.com/uK8yiYL.gif';

    $raw = implode(' ', $urls);

    // detect spotify protocol urls
    $raw = str_replace(
        ['spotify:track:'],
        ['http://open.spotify.com/track/'],
        $raw
    );

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

Route::get('weather', function () {
    $user = 'aroticoz';

    // ping the irc api
    $request = guzzleClient('post', 'https://www.darchoods.net/api/irc/user/view', [
        'username' => $user
    ]);

    $user = $request->json();
    if (!count($user) || ($accountName = array_get($user, 'data.user.account', null)) === null) {
        return Message::privmsg($command->message->channel(), color('Error: Location seems to be invalid, try again.'));
    }

    $authModel = Config::get('auth.model');
    $objUser = with(new $authModel)->whereUsername($accountName)->get()->first();


    echo \Debug::dump($objUser, '');
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
