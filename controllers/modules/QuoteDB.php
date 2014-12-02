<?php
use Cysha\Modules\Taylor\Helpers\Irc\Command as Command;
use Cysha\Modules\Taylor\Helpers\Irc\Message as Message;
use Cysha\Modules\Taylor\Helpers\Irc as Irc;

$trigger = \Config::get('taylor::bot.command_trigger', '>');

// setup a new client
$client = new GuzzleHttp\Client([
    'base_url' => 'https://www.darchoods.net/api/qdb/',
    'defaults' => ['headers' => ['X-Auth-Token' => Config::get('taylor::api.darchoods')]],
    'timeout'  => 2,
]);

Command::register($trigger.'quote', function (Command $command) use ($client) {
    $quote_id = $command->params[0];
    $url = 'search/byId';
    if ($quote_id == 0 || !ctype_digit((string)$quote_id)) {
        $url = 'random';
    }

    try {
        $request = $client->post($url, ['body' => [
            'channel' => $command->message->channel(),
            'quote_id' => $quote_id,
        ]]);
    } catch (\GuzzleHttp\Exception\ServerException $e) {
        return Message::privmsg($command->message->channel(), color('Error: Could not query the server.'));
    }

    if ($request->getStatusCode() != '200') {
        return Message::privmsg($command->message->channel(), color('Error: Could not query the server.'));
    }

    $data = $request->json();
    $quote = array_get($data, 'data.quote');
    if ($quote == false) {
        return Message::privmsg($command->message->channel(), color('Error: No Quotes found for channel.'));
    }

    return Message::privmsg($command->message->channel(), sprintf(
        'Quote#%s: %s',
        array_get($quote, 'quote_id', 0),
        array_get($quote, 'content')
    ));
});

Command::register($trigger.'addquote', function (Command $command) use ($client) {

    try {
        $request = $client->post('create', ['body' => [
            'channel' => $command->message->channel(),
            'author'  => $command->sender->nick,
            'quote'   => $command->text,
        ]]);
    } catch (\GuzzleHttp\Exception\ServerException $e) {
        return Message::privmsg($command->message->channel(), color('Error: Could not query the server.'));
    }
    if ($request->getStatusCode() != '200') {
        return Message::privmsg($command->message->channel(), color('Error: Could not query the server.'));
    }

    $data = $request->json();

    return Message::privmsg($command->message->channel(), sprintf(
        'Thank you for your submission. Your quote has been added as number %d',
        array_get($data, 'data.quote.quote_id', 0)
    ));
});
