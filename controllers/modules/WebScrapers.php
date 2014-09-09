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
