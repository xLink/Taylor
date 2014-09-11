<?php
use Cysha\Modules\Taylor\Helpers\Irc as Irc;

function message_debug($message, $title = false)
{
    echo Irc\ANSI::color(Irc\ANSI::RED, false, '--- Called '.($title ?: 'Debug')).PHP_EOL;
    if (!$message->sender) {
        echo Irc\ANSI::color(Irc\ANSI::BLUE, false, "[DEBUG] SENDER: ") . 'None' .PHP_EOL;
    } elseif ($message->sender->isServer()) {
        echo Irc\ANSI::color(Irc\ANSI::BLUE, false, "[DEBUG] SERVER: ") . $message->sender->server .PHP_EOL;
    } elseif ($message->sender->isUser()) {
        echo Irc\ANSI::color(Irc\ANSI::BLUE, false, "[DEBUG] NICK: ") . $message->sender->nick .PHP_EOL;
    }

    // Command
    echo Irc\ANSI::color(Irc\ANSI::BLUE, false, "[DEBUG] COMMAND: ") . $message->command .PHP_EOL;

    if (isset($message->params) && count($message->params)) {
        foreach ($message->params as $n => $p) {
            echo Irc\ANSI::color(Irc\ANSI::BLUE, false, "[DEBUG] PARAMS[$n]: ") . $message->params[$n] .PHP_EOL;
        }
    }
    var_dump($message);
}

function color($msg, $color = null)
{
    if (false) {
        return $msg;
    }

    switch($color){
        case 'white':    $return = chr(3).'00';          break;
        case 'black':    $return = chr(3).'01';          break;
        case 'navy':     $return = chr(3).'02';          break;
        case 'green':    $return = chr(3).'03';          break;
        case 'red':      $return = chr(3).'04';          break;
        case 'brown':    $return = chr(3).'05';          break;
        case 'purple':   $return = chr(3).'06';          break;
        case 'orange':   $return = chr(3).'07';          break;
        case 'yellow':   $return = chr(3).'08';          break;
        case 'lime':     $return = chr(3).'09';          break;
        case 'teal':     $return = chr(3).'10';          break;
        case 'aqua':     $return = chr(3).'11';          break;
        case 'blue':     $return = chr(3).'12';          break;
        case 'pink':     $return = chr(3).'13';          break;
        case 'dgrey':    $return = chr(3).'14';          break;
        case 'grey':     $return = chr(3).'15';          break;
        case 'rand':     $return = chr(3).rand(3, 15);   break;

        case 'normal':   $return = chr(15);              break;
        case 'bold':     $return = chr(2);               break;
        case 'underline':$return = chr(31);              break;
        default:         $return = chr(15);              break;
    }

    return $return.$msg.chr(3);
}

function strip_whitespace($msg)
{
    $msg = str_replace(["\n", "\r\n", "\r", "\t"], ' ', $msg);
    $msg = trim(preg_replace('/\s+/', ' ', $msg));
    return $msg;
}

function run_cmd($channel, $command, $params = [])
{
    if (is_array($params)) {
        $params = implode(' ', $params);
    }

    $callFunc = Irc\Message::parse(sprintf(': PRIVMSG %s :%s %s', $channel, $command, $params));
    return Irc\Command::make($callFunc)->run();
}
