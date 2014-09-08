<?php
use Cysha\Modules\Taylor\Helpers\Irc\Command as Command;
use Cysha\Modules\Taylor\Helpers\Irc\Message as Message;
use Cysha\Modules\Taylor\Helpers\Irc as Irc;

Command::register('>ping', function (Irc\Command $command) {
    return Irc\Message::privmsg($command->message->channel(), $command->sender->nick.': Pong');
});

if (true) {
    Message::listen('*', function (Irc\Message $message) {
        debug($message);
    });
}
