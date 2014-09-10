<?php
use Cysha\Modules\Taylor\Helpers\Irc\Command as Command;
use Cysha\Modules\Taylor\Helpers\Irc\Message as Message;
use Cysha\Modules\Taylor\Helpers\Irc as Irc;

$trigger = \Config::get('taylor::bot.command_trigger', '>');

Command::register($trigger.'ping', function (Command $command) {
    $response = number_format((microtime(true) - $command->time), 5, '.', '');
    return Message::privmsg($command->message->channel(), $command->sender->nick.': Pong ('.$response.' ms)');
});

if (false) {
    Message::listen('*', function (Message $message) {
        message_debug($message);
    });
}
