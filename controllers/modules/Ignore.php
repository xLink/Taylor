<?php
use Cysha\Modules\Taylor\Helpers\Irc\Command as Command;
use Cysha\Modules\Taylor\Helpers\Irc\Message as Message;
use Cysha\Modules\Taylor\Helpers\Irc as Irc;

Command::register($trigger.'ignored', function (Command $command) {
    return Message::privmsg($command->message->channel(), 'I am currently ignoring: '.implode(', ', Cache::get('taylor::bots.list', [])));
});

Command::register($trigger.'rmignore', function (Command $command) {
    if (!testForGod($command)) {
        return Message::privmsg($command->message->channel(), $command->sender->nick.': '.color('Fail.', 'red').'.');
    }

    $user = trim(strtolower($command->text));

    $botList = Cache::get('taylor::bots.list', []);
    Cache::forever('taylor::bots.list', array_diff($botList, array($user)));

    return Message::privmsg($command->message->channel(), 'I am currently ignoring: '.implode(', ', Cache::get('taylor::bots.list', [])));
});

Command::register($trigger.'registerBot', function (Command $command) {
    if (!testForGod($command)) {
        return Message::privmsg($command->message->channel(), $command->sender->nick.': '.color('Fail.', 'red').'.');
    }

    addToCache('taylor::bots.list', strtolower($command->text));
    return Message::privmsg($command->message->channel(), $command->sender->nick.': okay.');
});

Command::register($trigger.'amigod', function (Command $command) {
    return Message::privmsg($command->message->channel(), (testForGod($command) ? color('true', 'green') : color('false', 'red')));
});




// ignore bots
Message::listen('privmsg', function (Message $message) {

    // test if this user is a bot
    if (testForBot($message->sender->nick)) {
        return false;
    }

    $user = getUserData($message->sender->nick);
    if (!count($user)) {
        return;
    }

    if (array_get($user, 'data.user.is_bot', false) === true) {
        addToCache('taylor::bots.list', strtolower($message->sender->nick));
        return false;
    }

    return;
}, 1000);
