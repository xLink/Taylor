<?php

$namespace = 'Cysha\Modules\Taylor\Controllers';

require_once 'routes-admin.php';
require_once 'routes-api.php';
require_once 'routes-module.php';

use Goutte\Client;

Route::get('cmd-php', function () {
    require_once(app_path().'/modules/taylor/goutte.phar');

    $params[0] = 'mysql_connect';
    if (!function_exists($params[0])) {
        die($params[0].' isnt a PHP Function');
    }

    $url = 'http://php.net/function.'.$params[0];
    $crawler = with(new Client())->request('GET', $url);

    $parts = [
        'name'        => 'h1.refname',
        'signature'   => '.dc-description',
        'description' => '.dc-title',
        'version'     => 'p.verinfo',
        'return'      => '.returnvalues p.para',
    ];

    foreach ($parts as $key => $selector) {
        $parts[$key] = $crawler->filter($selector)->last()->text();
        $parts[$key] = str_replace(["\n", "\r\n", "\r", "\t"], ' ', $parts[$key]);
        $parts[$key] = preg_replace('~[^a-zA-Z0-9\(\)\[\]\$\£\@\!\^\&\*\-\=\_\+\`\{\}\"\;\'\.\<\>]+~', '', $parts[$key]);
        $parts[$key] = trim(preg_replace('/\s+/', ' ', $parts[$key]));
    }

    echo \Debug::dump([
        sprintf('( PHP Ver: %s ) %s — %s', $parts['version'], $parts['name'], $parts['description']),
        $parts['signature'],
        $parts['return'],
        $url
    ]);
});
