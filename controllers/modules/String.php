<?php
use Cysha\Modules\Taylor\Helpers\Irc\Command as Command;
use Cysha\Modules\Taylor\Helpers\Irc\Message as Message;
use Cysha\Modules\Taylor\Helpers\Irc as Irc;

$trigger = \Config::get('taylor::bot.command_trigger', '>');

Command::register($trigger.'base64_decode', function (Command $command) {
    if (empty($command->params[0]) || substr($command->params[0], 0, 1) == '?') {
        return Message::privmsg($command->message->channel(), 'Usage: <b64_string>');
    }

    $text = base64_decode($command->text);
    return Message::privmsg($command->message->channel(), color($text));
});

Command::register($trigger.'base64_encode', function (Command $command) {
    if (empty($command->params[0]) || substr($command->params[0], 0, 1) == '?') {
        return Message::privmsg($command->message->channel(), 'Usage: <text>');
    }

    $text = base64_encode($command->text);
    return Message::privmsg($command->message->channel(), color($text));
});

Command::register($trigger.'sha1', function (Command $command) {
    if (empty($command->params[0]) || substr($command->params[0], 0, 1) == '?') {
        return Message::privmsg($command->message->channel(), 'Usage: <text>');
    }

    $text = sha1($command->text);
    return Message::privmsg($command->message->channel(), color($text));
});

Command::register($trigger.'md5', function (Command $command) {
    if (empty($command->params[0]) || substr($command->params[0], 0, 1) == '?') {
        return Message::privmsg($command->message->channel(), 'Usage: <text>');
    }

    $text = md5($command->text);
    return Message::privmsg($command->message->channel(), color($text));
});

Command::register($trigger.'strlen', function (Command $command) {
    if (empty($command->params[0]) || substr($command->params[0], 0, 1) == '?') {
        return Message::privmsg($command->message->channel(), 'Usage: <text>');
    }

    $text = strlen($command->text);
    return Message::privmsg($command->message->channel(), sprintf('Given string is %d characters in length', $text));
});

Command::register($trigger.'hex2dec', function (Command $command) {
    if (empty($command->params[0]) || substr($command->params[0], 0, 1) == '?') {
        return Message::privmsg($command->message->channel(), 'Usage: <hex>');
    }
    if (empty($command->text)) {
        return Message::privmsg($command->message->channel(), 'You need give me something to turn into RGB.');
    }

    $color = str_replace('#', '', $command->params[0]);
    $ret = array(
        'r' => hexdec(substr($color, 0, 2)),
        'g' => hexdec(substr($color, 2, 2)),
        'b' => hexdec(substr($color, 4, 2))
    );
    $text =  '[ '.color('Red: '.$ret['r'], 'red')
            .' ][ '.color('Green: '.$ret['g'], 'green')
            .' ][ '.color('Blue: '.$ret['b'], 'blue').' ]';
    return Message::privmsg($command->message->channel(), $text);
});

Command::register($trigger.'str2hex', function (Command $command) {
    if (empty($command->params[0]) || substr($command->params[0], 0, 1) == '?') {
        return Message::privmsg($command->message->channel(), 'Usage: <hex>');
    }
    if (empty($command->text)) {
        return Message::privmsg($command->message->channel(), 'You need to give me something to hex.');
    }

    $split = str_split($command->text);
    $text = '';
    foreach ($split as $char) {
        $text .= '\x' . bin2hex($char);
    }
    return Message::privmsg($command->message->channel(), color($text));
});
