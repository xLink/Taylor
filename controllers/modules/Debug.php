<?php
use Cysha\Modules\Taylor\Helpers\Irc\Command as Command;
use Cysha\Modules\Taylor\Helpers\Irc\Message as Message;
use Cysha\Modules\Taylor\Helpers\Irc as Irc;

$trigger = \Config::get('taylor::bot.command_trigger', '>');

Command::register($trigger.'ping', function (Command $command) {
    $response = number_format((microtime(true) - $command->time), 5, '.', '');
    return Message::privmsg($command->message->channel(), $command->sender->nick.': Pong ('.$response.' ms)');
});

Command::register($trigger.'cmds', function (Command $command) {
    $functions = \Cache::get('taylor.functions', []);

    $channel = $command->message->channel();
    $msgs = [];
    $msgs[] = Message::privmsg($channel, '-----[ My Command List || Exec cmd with ? for usage ]-----');

    if (!count($functions)) {
        $msgs[] = Message::privmsg($channel, 'No functions found. Hrm...');
        return $msgs;
    }

    // run over the function list, add them to the output string till we reach the limit, then output and start again
    $str = null;
    foreach ($functions as $func) {
        if (strlen($str. ' ' .$func) >= 255) {
            $msgs[] = Message::privmsg($channel, $str);
            $str = null;
        }

        $str .= ' '.$func;
    }

    $msgs[] = Message::privmsg($channel, $str);

    return $msgs;
});

if (false) {
    Message::listen('*', function (Message $message) {
        message_debug($message);
    });
}
