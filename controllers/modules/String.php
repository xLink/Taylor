<?php
use Cysha\Modules\Taylor\Helpers\Irc\Command as Command;
use Cysha\Modules\Taylor\Helpers\Irc\Message as Message;
use Cysha\Modules\Taylor\Helpers\Irc as Irc;

Command::register('>base64_decode', function (Irc\Command $command) {
    return Irc\Message::privmsg($command->message->channel(), base64_decode($command->params[0]));
});

Command::register('>base64_encode', function (Irc\Command $command) {
    return Irc\Message::privmsg($command->message->channel(), base64_encode($command->params[0]));
});
