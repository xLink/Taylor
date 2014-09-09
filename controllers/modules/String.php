<?php
use Cysha\Modules\Taylor\Helpers\Irc\Command as Command;
use Cysha\Modules\Taylor\Helpers\Irc\Message as Message;
use Cysha\Modules\Taylor\Helpers\Irc as Irc;

$trigger = \Config::get('taylor::bot.command_trigger', '>');

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
    $text =  '[ '.color('Red: '.$ret['r'], 'red').' ]'.
             '[ '.color('Green: '.$ret['g'], 'green').' ]'.
             '[ '.color('Blue: '.$ret['b'], 'blue').' ]';
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

Message::listen('privmsg', function ($message) {
    $whitelist = [
        'md5', 'sha1', 'strlen', 'base64_decode', 'base64_encode', 'str_replace', 'number_format',
        'ucwords', 'substr', 'strrev', 'str_shuffle', 'str_split', 'explode', 'chr', 'addslashes', 'addcslashes',
        'hex2bin', 'rand'
    ];

    // make sure we have some params
    if (count($message->params) == 2) {
        list($channel, $params) = $message->params;
    }

    // explode the params & grab the function
    $params = explode(' ', $params);
    $command = strtolower(array_shift($params));

    // if we dont have a > ignore this msg
    if (substr($command, 0, 1) != '>') {
        return;
    }
    $command = substr($command, 1);

    // if the function isnt in the whitelist ignore this msg
    if (!in_array($command, $whitelist)) {
        return;
    }

    // make sure it's only checking PHPs functions and not self defined
    $functions = array_get(get_defined_functions(), 'internal');
    if (!in_array($command, $functions)) {
        return Message::privmsg($channel, color($command.' isnt a PHP Function'));
    }

    // call the function and make sure we dont get false
    $message = null;
    try {
        $return = call_user_func_array($command, $params);

        if (is_array($return)) {
            $return = '[\''.implode('\', \'', $return).'\']';
        }

    } catch (ErrorException $e) {
        $return = false;
        $message = $e->getMessage();
    }

    return Message::privmsg($channel, color(sprintf(
        'PHP> %s(%s) // %s',
        $command,
        count($params) ? '\''.implode('\', \'', $params).'\'' : null,
        $return === false ? color($message, 'red') : color($return, 'green')
    )));
});
