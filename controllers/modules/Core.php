<?php
use Cysha\Modules\Taylor\Helpers\Irc\Command as Command;
use Cysha\Modules\Taylor\Helpers\Irc\Message as Message;
use Cysha\Modules\Taylor\Helpers\Irc as Irc;

// join channel on invite
Message::listen('invite', function (Message $message) {
    return Message::join($message->params[1]);
});

// if i get kicked, rejoin the channel
Message::listen('kick', function ($message) {
    if ($message->params[1] == \Config::get('taylor::bot.nick')) {
        return Message::join($message->params[0]);
    }
});
