<?php
require_once(app_path().'/modules/taylor/goutte.phar');
use Cysha\Modules\Taylor\Helpers\Irc\Command as Command;
use Cysha\Modules\Taylor\Helpers\Irc\Message as Message;
use Cysha\Modules\Taylor\Helpers\Irc as Irc;
use Symfony\Component\DomCrawler\Crawler;

$trigger = \Config::get('taylor::bot.command_trigger', '>');

Message::listen('privmsg', function ($message) {
    if (!isset($message->params[1])) {
        return;
    }

    preg_match_all("@\b(https?://)?(([0-9a-zA-Z_!~*'().&=+$%-]+:)?[0-9a-zA-Z_!~*'().&=+$%-]+\@)?(([0-9]{1,3}\.){3}[0-9]{1,3}|([0-9a-zA-Z_!~*'()-]+\.)*([0-9a-zA-Z][0-9a-zA-Z-]{0,61})?[0-9a-zA-Z]\.[a-zA-Z]{2,6})(:[0-9]{1,4})?((/[0-9a-zA-Z_!~*'().;?:\@&=+$,%#-]+)*/?)@", $message->params[1], $urls);

    if (!count($urls[0])) {
        return;
    }

    $msgs = [];
    foreach ($urls[0] as $url) {
        $msgSet = [];
        Event::fire('taylor::privmsg: urlDetection', array($url, &$msgSet));

        if (count($msgSet)) {
            var_dump($msgSet);
            $msgs[] = Message::privmsg($message->params[0], color($msgSet['title']));
        }
    }

    return $msgs;
});

/** @author infyhr **/
// detect images & resolutions
Event::listen('taylor::privmsg: urlDetection', function ($url, &$msgSet) {

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_ENCODING, '');
    curl_setopt($ch, CURLOPT_USERAGENT, 'Taylor/v4.0');
    curl_setopt($ch, CURLOPT_FAILONERROR, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $extension = explode('.', $url);
    if ($extension) {
        $extension = end($extension);
    } else {
        return;
    }

    switch(strtolower($extension)) {
        case '.png':
            curl_setopt($ch, CURLOPT_RANGE, '0-24'); // 24B = 192b = 0.024kB
            break;
        case '.gif':
            curl_setopt($ch, CURLOPT_RANGE, '0-10'); // 10B = 80b = 0.01kB
            break;
        default:
            curl_setopt($ch, CURLOPT_RANGE, '0-32768'); // 32 kB
            break;
    }


    $data = curl_exec($ch);
    if (!$data) {
        return;
    }

    $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    $return = null;
    switch($content_type) {
        case 'image/png':
            $res = getPNGImageXY($data);
            if ($res) {
                $return .= sprintf('Content-Type: image/png, Resolution: %dx%d', $res[0], $res[1]);
            }
            break;

        case 'image/gif':
            $res = getGIFImageXY($data);
            if ($res) {
                $return .= sprintf('Content-Type: image/gif, Resolution: %dx%d', $res[0], $res[1]);
            }
            break;

        default:
            $img = @imagecreatefromstring($data);
            if ($img) {
                $res = [imagesx($img), imagesy($img)];
                if ($res) {
                    $return .= sprintf('Content-Type: %s, Resolution: %dx%d', $content_type, $res[0], $res[1]);
                }
            }
            break;
    }

    if (is_null($return)) {
        return;
    }

    $msgSet = [
        'mode'   => 'img detection',
        'title'  => $return,
    ];
    return false;
}, 5);


// detect youtube links
Event::listen('taylor::privmsg: urlDetection', function ($url, &$msgSet) {
    if (!strpos($url, 'youtube.co') && !strpos($url, 'youtu.be')) {
        return;
    }

    /**
     * http://www.youtube.com/v/<YOUTUBE_ID>?fs=1&amp;hl=en_US&amp;rel=0
     * http://www.youtube.com/embed/<YOUTUBE_ID>?rel=0
     * http://www.youtube.com/watch?v=<YOUTUBE_ID>&feature=feedrec_grec_index
     * http://www.youtube.com/watch?v=<YOUTUBE_ID>
     * http://youtu.be/<YOUTUBE_ID>
     * http://www.youtube.com/watch?v=<YOUTUBE_ID>#t=0m10s
     * http://www.youtube.com/user/IngridMichaelsonVEVO#p/a/u/1/<YOUTUBE_ID>
     */
    if (!preg_match('/.*(?:youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=)([^#\&\?]*?).*/U', $url, $matches)) {
        return;
    }

    $request = with(new Goutte\Client())->request('GET', 'http://www.youtube.com/watch?v='.$matches[1]);
    if (($request instanceof Symfony\Component\DomCrawler\Crawler) === false) {
        return;
    }
    $title  = getNode($request, '#eow-title', null);
    $length = inBetween('"length_seconds": "', '"', $request->html()) ?: 0;

    $msgSet = [
        'mode'   => 'youtube',
        'title'  => '[ You'.color('Tube', 'red').' - '.implode(' | ', [$title, 'Length: '.secs_to_h($length)]).' ]',
    ];
    return false;
}, 10);

// detect github links
Event::listen('taylor::privmsg: urlDetection', function ($url, &$msgSet) {
    if (!strpos($url, 'github.com/') !== false) {
        return;
    }

    $request = with(new Goutte\Client())->request('GET', $url);
    if (($request instanceof Symfony\Component\DomCrawler\Crawler) === false) {
        return;
    }

    $return = null;
    switch (true) {
        // https://github.com/<username>/<repo>/issues/<issue_id>
        case (strpos($url, '/issues') !== false):
            // check if its an issue, or an issue list
            $issue = $request->filter('.js-issue-title')->count();
            if ($issue) {
                $return = strip_whitespace(sprintf(
                    '%s%s: %s by %s (State: %s, Comments: %d)',
                    getNode($request, 'h1.entry-title.public', null),
                    getNode($request, 'h1.gh-header-title span.gh-header-number', 0),
                    getNode($request, 'h1.gh-header-title span.js-issue-title', null),
                    getNode($request, 'a.author', 0),
                    getNode($request, 'div.gh-header-meta .state', 0),
                    0
                ));
            }
        break;

        // https://github.com/<username>/<repo>/pull/<pull_id>
        case (strpos($url, '/pull/') !== false):
            $return = strip_whitespace(sprintf(
                '%s%s: %s (State: %s, Comments: %d, Commits: %d, Files Changed: %d)',
                getNode($request, 'h1.entry-title.public', null),
                getNode($request, 'h1.gh-header-title span.gh-header-number', 0),
                getNode($request, 'h1.gh-header-title span.js-issue-title', null),
                getNode($request, 'div.gh-header-meta .state', 0),
                getNode($request, 'span#conversation_tab_counter', 0),
                getNode($request, 'span#commits_tab_counter', 0),
                getNode($request, 'span#files_tab_counter', 0)
            ));
        break;

        // https://github.com/<username>/<repo>/pulls
        case (strpos($url, '/pulls') !== false):
            $return = strip_whitespace(sprintf(
                '%s: Pull Requests - %s - %s',
                getNode($request, 'h1.entry-title.public', null),
                getNode($request, '.table-list-header-toggle.states a:first-child', 0),
                getNode($request, '.table-list-header-toggle.states a:last-child', 0)
            ));
        break;

        // https://github.com/<username>/<repo>/commit/<commit_hash>
        case (strpos($url, '/commit/') !== false):
            $return = strip_whitespace(sprintf(
                '%s: %s by %s (%s)',
                getNode($request, 'h1.entry-title.public', null),
                getNode($request, '.commit-title', null),
                getNode($request, '.author-name a[rel="author"]', null),
                getNode($request, '.toc-diff-stats button', null)
            ));
        break;

        // https://github.com/<username>/<repo>/blob/<branch>/<path/to/file.ext>
        case (strpos($url, '/blob/') !== false):
            $return = strip_whitespace(sprintf(
                '%s: %s (Lines: %s, Size: %s)',
                getNode($request, 'h1.entry-title.public', null),
                getNode($request, '.breadcrumb .final-path', null),
                getNode($request, '.info.file-name span:first-child', '0 lines'),
                getNode($request, '.info.file-name span:last-child', '0.00 kb')
            ));
        break;

        // https://github.com/<username>/<repo>/
        case (preg_match('/github.com\/.*\/.*$/U', $url)):
            $return = strip_whitespace(sprintf(
                '%s: (Starred: %d, Repo Forks: %d, Commits: %d, Contributors: %d)',
                getNode($request, 'h1.entry-title.public', null),
                getNode($request, 'ul.pagehead-actions li:nth-child(2) a.social-count', 0),
                getNode($request, 'ul.pagehead-actions li:nth-child(2) a.social-count', 0),
                getNode($request, '.stats-switcher-wrapper li:nth-child(1) span.num', 0),
                getNode($request, '.stats-switcher-wrapper li:nth-child(4) span.num', 0)
            ));
        break;

        // https://github.com/<username>
        // https://github.com/<organisation>
        case (preg_match('/github.com\/.*$/U', $url)):
            //check to see if profile is organisation or user
            $orgTest = $request->filter('h1.org-name')->count();
            if ($orgTest) {
                $return = sprintf(
                    'Github Organisation: %s / People: %d',
                    getNode($request, 'span.js-username', null),
                    getNode($request, 'span.org-stats', 0)
                );

            //else profile!
            } else {
                $return = sprintf(
                    'Github User: %s (Followers: %d, Starred: %d, Following: %d, Year of Contributions: %d, Joined: %s)',
                    getNode($request, '.js-username .vcard-username', null),
                    getNode($request, '.vcard-stats a:nth-child(1) .vcard-stat-count', 0),
                    getNode($request, '.vcard-stats a:nth-child(2) .vcard-stat-count', 0),
                    getNode($request, '.vcard-stats a:nth-child(3) .vcard-stat-count', 0),
                    getNode($request, '.table-column.contrib-day .num', 0),
                    getNode($request, '.js-username ul.vcard-details li:last-child', 0)
                );
            }
        break;

    }

    if (is_null($return)) {
        return;
    }

    $msgSet = [
        'mode'   => 'github',
        'title'  => $return,
    ];
    return false;
}, 10);


// normal links
Event::listen('taylor::privmsg: urlDetection', function ($url, &$msgSet) {

    $request = with(new Goutte\Client())->request('GET', $url);
    if (($request instanceof Symfony\Component\DomCrawler\Crawler) === false) {
        return;
    }

    $title = null;

    try {
        $title = getNode($request, 'title', null);
    } catch (\InvalidArgumentException $e) {
        return;
    }

    if ($title == '404 Not Found') {
        return;
    }

    if (!empty($title)) {
        $msgSet = [
            'mode'  => 'url',
            'title' => $title,
        ];
        return false;
    }

    return;
}, 10);



/** Helpers **/

function secs_to_h($secs)
{
    $units = array(
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


function getNode($request, $selector, $default = null)
{
    return $request->filter($selector)->count() ? strip_whitespace($request->filter($selector)->first()->text()) : $default;
}
