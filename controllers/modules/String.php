<?php
use Cysha\Modules\Taylor\Helpers\Irc\Command as Command;
use Cysha\Modules\Taylor\Helpers\Irc\Message as Message;
use Cysha\Modules\Taylor\Helpers\Irc as Irc;

$trigger = \Config::get('taylor::bot.command_trigger', '>');

Command::register($trigger.'hex2dec', function (Command $command) {
    if (empty($command->params) || substr($command->params[0], 0, 1) == '?') {
        return Message::privmsg($command->message->channel(), 'Usage: <hex>');
    }

    $color = str_replace('#', '', $command->params[0]);
    $ret = array(
        'r' => hexdec(substr($color, 0, 2)),
        'g' => hexdec(substr($color, 2, 2)),
        'b' => hexdec(substr($color, 4, 2))
    );
    $text =  '[ '.color('Red: '.$ret['r'], 'red').' ]'.
             '[ '.color('Green: '.$ret['g'], 'green').' ]'.
             '[ '.color('Blue: '.$ret['b'], 'blue').' ]'.
             ' - http://placehold.it/100x100/'.$color.'/&text=%20&.gif';
    return Message::privmsg($command->message->channel(), $text);
});

Command::register($trigger.'str2hex', function (Command $command) {
    if (empty($command->params) || substr($command->params[0], 0, 1) == '?') {
        return Message::privmsg($command->message->channel(), 'Usage: <hex>');
    }

    $split = str_split($command->text);
    $text = '';
    foreach ($split as $char) {
        $text .= '\x' . bin2hex($char);
    }
    return Message::privmsg($command->message->channel(), color($text));
});

Message::listen('privmsg', function ($message) {
    $whitelist = \Config::get('taylor::php', []);
    if (!count($whitelist)) {
        return;
    }

    // register the php functions
    if (\Cache::get('taylor.phpfuncs', true)) {
        $functions = Cache::get('taylor.functions', []);
        foreach ($whitelist as $phpfunc) {
            $functions[] = '>'.$phpfunc;
        }
        \Cache::forever('taylor.functions', array_unique($functions));
        \Cache::forever('taylor.phpfuncs', false);
    }

    // make sure we have some params
    if (count($message->params) == 2) {
        list($channel, $params) = $message->params;
    }

    // if we dont have a #channel ignore the msg
    if (substr($channel, 0, 1) != '#') {
        return;
    }

    // explode the params & grab the function
    $arg_parse = arg_parse($params);
    if ($arg_parse === false) {
        return;
    }
    list($command, $params) = $arg_parse;

    // if we dont have a > ignore this msg
    if (substr($command, 0, 1) != '>') {
        return;
    }
    $command = substr($command, 1);

    // if the function isnt in the whitelist ignore this msg
    if (!in_array($command, $whitelist)) {
        return;
    }

    // if (>$whitelist ?) then run >php $whitelist
    if (($test = explode(' ', $message->params[1])) !== false && count($test) == 2 && $test[1] == '?') {
        return run_cmd($channel, '>php', substr($test[0], 1));
    }

    // make sure it's only checking PHPs functions and not self defined
    //$functions = array_get(get_defined_functions(), 'internal');
    //if (!in_array($command, $functions)) {
    //    return Message::privmsg($channel, color($command.' isnt a PHP Function'));
    //}

    // call the function and make sure we dont get false
    $message = null;
    try {
        $command = strpos($command, '::') ? ucwords($command) : $command;
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

/**
 * Does a custom argument parsing for the php function set above
 *
 * @return array
 */
function arg_parse($line)
{
    // Explode by space first in order to separate the arguments from the actual command.
    $data = !is_array($line) ? explode(' ', $line, 2) : $line;
    if (count($data) < 2) {
        return false;
    }

    $command = null;
    $args = [];
    $matches = [];
    list($command, $args) = $data;

    // try grabbing all arguments
    preg_match_all('/"(.*?)"|\'(.*?)\'/s', $args, $matches);

    // if we get nothing, roll with a nice explode
    if (empty($matches[0])) {
        $matches = explode(' ', $args);
    } else {
        $matches = array_get($matches, (!empty($matches[1][0]) ? '1' : '2'));
    }

    return [$command, $matches];
}
