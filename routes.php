<?php
use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;

$namespace = 'Cysha\Modules\Taylor\Controllers';

require_once 'routes-admin.php';
require_once 'routes-api.php';
require_once 'routes-module.php';

require_once(app_path().'/modules/taylor/goutte.phar');

Route::get('cmd', function () {
    $msg = "['a', 'b', 'c']";

    echo \Debug::dump($msg, '');
    echo \Debug::dump((array)$msg, '');
});
