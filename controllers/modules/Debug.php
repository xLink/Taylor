<?php
use Cysha\Modules\Taylor\Helpers\Irc\Command as Command;
use Cysha\Modules\Taylor\Helpers\Irc\Message as Message;
use Cysha\Modules\Taylor\Helpers\Irc as Irc;

$trigger = \Config::get('taylor::bot.command_trigger', '>');

Command::register($trigger.'ping', function (Command $command) {
    return Message::privmsg($command->message->channel(), $command->sender->nick.': Pong');
});

if (true) {
    Message::listen('*', function (Message $message) {
        debug($message);
    });
}
