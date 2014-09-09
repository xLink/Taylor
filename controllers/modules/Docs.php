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
    if (!in_array($command->params[0], $functions)) {
        return Message::privmsg($command->message->channel(), color($command->params[0].' isnt a PHP Function'));
    }

    // do the request
    $url = 'http://php.net/function.'.$command->params[0];
    $crawler = with(new Goutte\Client())->request('GET', $url);

    // setup a list of parts to scrap from the page
    $parts = [
        'version'     => 'p.verinfo',
        'name'        => 'h1.refname',
        'description' => '.dc-title',
        'signature'   => '.dc-description',
        'return'      => '.returnvalues p.para',
    ];

    // do the scraping
    foreach ($parts as $key => $selector) {
        $parts[$key] = strip_whitespace($crawler->filter($selector)->last()->text());
    }

    // return the goodness :D
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

    // assign args 0 & 1 to variables
    list($packageName, $methodName) = $command->params;

    //query the url in question, and make sure we get a valid response
    $url = sprintf('http://golang.org/pkg/%s/#%s', $packageName, $methodName);
    $response = with(new Client())->get($url);
    if ($response->getStatusCode() != '200') {
        return Message::privmsg($command->message->channel(), color('Error: Could not query the server.'));
    }

    // strtolower the response, and test to see if we have an span.alert telling us NOPE
    $crawler = new Crawler(strtolower($response->getBody(true)));
    if (($alert = $crawler->filter('span.alert')->count()) !== 0) {
        return Message::privmsg($command->message->channel(), color('Function Not Found'));
    }

    // setup a list of parts to scrap from the page
    $parts = [
        'usage'     => sprintf('#%s + pre', $methodName),
        'details'   => sprintf('#%s + pre + p', $methodName),
    ];

    // do the scraping
    foreach ($parts as $key => $selector) {
        $parts[$key] = $crawler->filter($selector)->count() > 0
                        ? strip_whitespace($crawler->filter($selector)->first()->text())
                        : null;
    }

    // return the goodness :D
    $msgs[] = Message::privmsg($command->message->channel(), color($parts['usage']));
    $msgs[] = Message::privmsg($command->message->channel(), color($parts['details']));
    $msgs[] = Message::privmsg($command->message->channel(), color($url));

    return $msgs;
});
