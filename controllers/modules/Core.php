<?php
use Cysha\Modules\Taylor\Helpers\Irc\Command as Command;
use Cysha\Modules\Taylor\Helpers\Irc\Message as Message;
use Cysha\Modules\Taylor\Helpers\Irc as Irc;

Message::listen('invite', function (Irc\Message $message) {
    return Irc\Message::join($message->params[1]);
});
