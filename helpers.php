<?php

function testForGod(Cysha\Modules\Taylor\Helpers\Irc\Command $command)
{
    if (Config::get('taylor::bot.god_mask', null) === null) {
        return false;
    }

    if ($command->sender->user.'@'.$command->sender->host === Config::get('taylor::bot.god_mask', null)) {
        return true;
    }

    return false;
}

function testForBot($clientNick)
{
    $botsList = Cache::get('taylor::bots.list', []);

    // if botlist has something in, test to see if author is in there
    if (!empty($botsList) && in_array(strtolower($clientNick), $botsList)) {
        return true;
    }

    return false;
}

function addToCache($key, $value)
{
    $values = Cache::get($key, []);
    $values[] = $value;
    Cache::forever($key, array_unique($values));
}

function getUserData($username)
{
    // if we get this far, the user isnt on the list, lets see if they should be
    $client = new GuzzleHttp\Client([
        'base_url' => 'https://www.darchoods.net/api/irc/',
        'defaults' => ['headers' => ['X-Auth-Token' => Config::get('taylor::api.darchoods')]],
        'timeout'  => 2,
    ]);

    try {
        $request = $client->post('user/view', ['body' => [
            'username' => $username
        ]]);
    } catch (\GuzzleHttp\Exception\ClientException $e) {
        return false;
    } catch (\GuzzleHttp\Exception\RequestException $e) {
        return false;
    } catch (InvalidArgumentException $e) {
        return false;
    } catch (ErrorException $e) {
        return false;
    } catch (Exception $e) {
        return false;
    }

    if ($request->getStatusCode() != '200') {
        return false;
    }

    return $request->json();
}
