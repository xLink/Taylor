<?php
require_once(app_path().'/modules/taylor/goutte.phar');
use Cysha\Modules\Taylor\Helpers\Irc\Command as Command;
use Cysha\Modules\Taylor\Helpers\Irc\Message as Message;
use Cysha\Modules\Taylor\Helpers\Irc as Irc;
use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;

$trigger = \Config::get('taylor::bot.command_trigger', '>');

Command::register($trigger.'php', function (Command $command) {
    if (!count($command->params) || substr($command->params[0], 0, 1) == '?') {
        return Message::privmsg($command->message->channel(), 'Usage: <function>');
    }

    // make sure it's only checking PHPs functions and not self defined
    $functions = array_get(get_defined_functions(), 'internal');
    if (!in_array($functions, $command->params[0])) {
        return Message::privmsg($command->message->channel(), color($command->params[0].' isnt a PHP Function'));
    }

    $url = 'http://php.net/function.'.$command->params[0];
    $crawler = with(new Goutte\Client())->request('GET', $url);

    $parts = [
        'version'     => 'p.verinfo',
        'name'        => 'h1.refname',
        'description' => '.dc-title',
        'signature'   => '.dc-description',
        'return'      => '.returnvalues p.para',
    ];

    foreach ($parts as $key => $selector) {
        $parts[$key] = rtrim(trim($crawler->filter($selector)->last()->text()));
        $parts[$key] = strip_whitespace($parts[$key]);
    }

    $msgs[] = Message::privmsg($command->message->channel(), color(sprintf(
        '( PHP Ver: %s ) %s — %s', $parts['version'], $parts['name'], $parts['description']
    )));
    $msgs[] = Message::privmsg($command->message->channel(), color($parts['signature']));
    $msgs[] = Message::privmsg($command->message->channel(), color($parts['return']));
    $msgs[] = Message::privmsg($command->message->channel(), color($url));

    return $msgs;
});

Command::register($trigger.'golang', function (Command $command) {
    if (!count($command->params) || substr($command->params[0], 0, 1) == '?') {
        return Message::privmsg($command->message->channel(), 'Usage: <function>');
    }

    list($packageName, $methodName) = ['fmt', 'println'];#$command->params;

    $url = sprintf('http://golang.org/pkg/%s/#%s', $packageName, $methodName);
    $response = with(new Client())->get($url);
    if ($response->getStatusCode() != '200') {
        return Message::privmsg($command->message->channel(), color('Function Not Found'));
    }

    $crawler = new Crawler(strtolower($response->getBody(true)));
    if (($alert = $crawler->filter('span.alert')->count()) !== 0) {
        return Message::privmsg($command->message->channel(), color('Function Not Found'));
    }

    $parts = [
        'usage'     => sprintf('#%s + pre', $methodName),
        'details'   => sprintf('#%s + pre + p', $methodName),
    ];

    foreach ($parts as $key => $selector) {
        $parts[$key] = $crawler->filter($selector)->count() > 0 ? strip_whitespace($crawler->filter($selector)->first()->text()) : null;
    }

    $msgs[] = Message::privmsg($command->message->channel(), color($parts['usage']));
    $msgs[] = Message::privmsg($command->message->channel(), color($parts['details']));
    $msgs[] = Message::privmsg($command->message->channel(), color($url));

    return $msgs;
});
