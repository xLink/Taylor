<?php
require_once(app_path().'/modules/taylor/goutte.phar');
use Cysha\Modules\Taylor\Helpers\Irc\Command as Command;
use Cysha\Modules\Taylor\Helpers\Irc\Message as Message;
use Cysha\Modules\Taylor\Helpers\Irc as Irc;

$trigger = \Config::get('taylor::bot.command_trigger', '>');

Command::register($trigger.'php', function (Command $command) {
    if (!count($command->params)) {
        return Message::privmsg($command->message->channel(), 'Usage: <function>');
    }

    if (!function_exists($command->params[0])) {
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
