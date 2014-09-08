<?php
use Cysha\Modules\Taylor\Helpers\Irc as Irc;

function debug($message, $title = false)
{
    echo Irc\ANSI::color(Irc\ANSI::RED, false, '--- Called '.($title ?: 'Debug')).PHP_EOL;
    if (!$message->sender) {
        echo Irc\ANSI::color(Irc\ANSI::BLUE, false, "[DEBUG] SENDER: ") . 'None' .PHP_EOL;
    } elseif ($message->sender->isServer()) {
        echo Irc\ANSI::color(Irc\ANSI::BLUE, false, "[DEBUG] SERVER: ") . $message->sender->server .PHP_EOL;
    } elseif ($message->sender->isUser()) {
        echo Irc\ANSI::color(Irc\ANSI::BLUE, false, "[DEBUG] NICK: ") . $message->sender->nick .PHP_EOL;
    }

    // Command
    echo Irc\ANSI::color(Irc\ANSI::BLUE, false, "[DEBUG] COMMAND: ") . $message->command .PHP_EOL;

    if (isset($message->params) && count($message->params)) {
        foreach ($message->params as $n => $p) {
            echo Irc\ANSI::color(Irc\ANSI::BLUE, false, "[DEBUG] PARAMS[$n]: ") . $message->params[$n] .PHP_EOL;
        }
    }
    var_dump($message);
}
