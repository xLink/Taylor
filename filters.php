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

/**
function secs_to_h($secs)
{
    $units = array(
        'year'   => 365*24*3600,
        'month'  => 30*24*3600,
        'week'   => 7*24*3600,
        'day'    => 24*3600,
        'hour'   => 3600,
        'minute' => 60,
        'second' => 1,
    );

    // specifically handle zero
    if ($secs == 0) {
        return '0 seconds';
    }

    $s = '';

    foreach ($units as $name => $divisor) {
        if ($quot = intval($secs / $divisor)) {
            $s .= $quot.' '.$name;
            $s .= (abs($quot) > 1 ? 's' : '') . ', ';
            $secs -= $quot * $divisor;
        }
    }

    return substr($s, 0, -2);
}
*/

/** img helpers **/
function getPNGImageXY($data)
{
    //The identity for a PNG is 8Bytes (64bits)long
    $ident = unpack('Nupper/Nlower', $data);
    //Make sure we get PNG
    if ($ident['upper'] !== 0x89504E47 || $ident['lower'] !== 0x0D0A1A0A) {
        return false;
    }

    //Get rid of the first 8 bytes that we processed
    $data = substr($data, 8);
    //Grab the first chunk tag, should be IHDR
    $chunk = unpack('Nlength/Ntype', $data);
    //IHDR must come first, if not we return false
    if ($chunk['type'] === 0x49484452) {
        //Get rid of the 8 bytes we just processed
        $data = substr($data, 8);
        //Grab our x and y
        $info = unpack('NX/NY', $data);
        //Return in common format
        return array($info['X'], $info['Y']);
    } else {
        return false;
    }
}

function getGIFImageXY($data)
{
    // The identity for a GIF is 6bytes (48Bits)long
    $ident = unpack('nupper/nmiddle/nlower', $data);
    // Make sure we get GIF 87a or 89a
    if ($ident['upper'] !== 0x4749 || $ident['middle'] !== 0x4638 || ($ident['lower'] !== 0x3761 && $ident['lower'] !== 0x3961)) {
        return false;
    }
    // Get rid of the first 6 bytes that we processed
    $data = substr($data, 6);
    // Grab our x and y, GIF is little endian for width and length
    $info = unpack('vX/vY', $data);
    // Return in common format
    return array($info['X'], $info['Y']);
}

/** goutte helper **/
function getNode($request, $selector, $default = null)
{
    return $request->filter($selector)->count() ? strip_whitespace($request->filter($selector)->first()->text()) : $default;
}

function goutteClient()
{
    $client = new Goutte\Client();
    $client->getClient()->setDefaultOption('config', ['curl' => ['CURLOPT_TIMEOUT' => 2]]);

    return $client;
}

function goutteRequest(Goutte\Client $client, $url, $method = 'get')
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }

    try {
        $request = $client->request(strtoupper($method), $url);
    } catch (GuzzleHttp\Exception\RequestException $e) {
        return -1;
    } catch (InvalidArgumentException $e) {
        return -1;
    } catch (Exception $e) {
        return -1;
    }

    if (($request instanceof Symfony\Component\DomCrawler\Crawler) === false) {
        return -2;
    }

    if ($client->getResponse()->getStatus() != '200') {
        return -3;
    }

    return $request;
}

function guzzleClient($method, $url, $data = [])
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }

    if (count($data)) {
        $data = ['body' => $data];
    }

    try {
        $response = with(new GuzzleHttp\Client())->$method($url, $data);
    } catch (\GuzzleHttp\Exception\ClientException $e) {
        return -1;
    } catch (Exception $e) {
        return -0;
    }

    if ($response->getStatusCode() != '200') {
        return -2;
    }

    return $response;
}

function getHumanReadableSize($size, $unit = null, $decimals = 2)
{
    $byteUnits = ['B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
    if (!is_null($unit) && !in_array($unit, $byteUnits)) {
        $unit = null;
    }
    $extent = 1;
    foreach ($byteUnits as $rank) {
        if ((is_null($unit) && ($size < $extent <<= 10)) || ($rank == $unit)) {
            break;
        }
    }
    return number_format($size / ($extent >> 10), $decimals) . $rank;
}
