<?php
use Cysha\Modules\Taylor\Helpers\Irc\Command as Command;
use Cysha\Modules\Taylor\Helpers\Irc\Message as Message;
use Cysha\Modules\Taylor\Helpers\Irc as Irc;

$trigger = Config::get('taylor::bot.command_trigger', '>');

Command::register($trigger.'setWeather', function (Command $command) {
    if (empty($command->params[0]) || substr($command->params[0], 0, 1) == '?') {
        return Message::privmsg($command->message->channel(), 'Usage: <location>');
    }

    // get the account name for the user that did this command
    $client = new GuzzleHttp\Client([
        'base_url' => 'https://www.darchoods.net/api/irc/',
        'defaults' => ['headers' => ['X-Auth-Token' => Config::get('taylor::api.darchoods')]],
        'timeout'  => 2,
    ]);

    try {
        $request = $client->post('user/view', ['body' => [
            'username' => $command->sender->nick
        ]]);
    } catch (\GuzzleHttp\Exception\ServerException $e) {
        return Message::privmsg($command->message->channel(), color('Error: IRC API appears to be down, Try again later.'));
    }

    $user = $request->json();
    if (!count($user) || ($accountName = array_get($user, 'data.user.account', null)) === null) {
        return Message::privmsg(
            $command->message->channel(),
            color('Error: I am not sure who you are, Login to https://www.darchoods.net with your IRC Credentials to add your account to my database.')
        );
    }

    $userModel = \Config::get('auth.model');
    $objUser = with(new $userModel)->whereNick($accountName)->first();
    if (!count($objUser)) {
        return Message::privmsg(
            $command->message->channel(),
            color('Error: I am not sure who you are, Login to https://www.darchoods.net with your IRC Credentials to add your account to my database.')
        );
    }

    $objUser->weather = $command->text;

    $save = $objUser->save();
    if ($save === true) {
        return Message::privmsg($command->message->channel(), $command->sender->nick.', your weather location has been saved.');
    }
    return Message::privmsg($command->message->channel(), $command->sender->nick.', there was an error saving your location. Please try again.');
});
