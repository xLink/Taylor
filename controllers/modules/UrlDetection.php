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

    $raw = $message->params[1];

    // detect spotify protocol urls
    $raw = str_replace(
        ['spotify:track:'],
        ['http://open.spotify.com/track/'],
        $raw
    );

    preg_match_all("@\b(https?://)?(([0-9a-zA-Z_!~*'().&=+$%-]+:)?[0-9a-zA-Z_!~*'().&=+$%-]+\@)?(([0-9]{1,3}\.){3}[0-9]{1,3}|([0-9a-zA-Z_!~*'()-]+\.)*([0-9a-zA-Z][0-9a-zA-Z-]{0,61})?[0-9a-zA-Z]\.[a-zA-Z]{2,6})(:[0-9]{1,4})?((/[0-9a-zA-Z_!~*'().;?:\@&=+$,%#-]+)*/?)@", $raw, $urls);

    if (!count($urls[0])) {
        return;
    }

    $msgs = [];
    foreach ($urls[0] as $url) {
        if (preg_match('(10\.(0|[1-9]|1[0-9]|2[0-4][0-9]|25[0-5]|1[0-9][0-9]?)\.(0|[1-9]|1[0-9]|2[0-4][0-9]|25[0-5]|1[0-9][0-9]?)\.(0|[1-9]|1[0-9]|2[0-4][0-9]|25[0-5]|1[0-9][0-9]?))', $url)) {
            continue;
        }

        if (preg_match('(192\.168\.(0|[1-9]|1[0-9]|2[0-4][0-9]|25[0-5]|1[0-9][0-9]?)\.(0|[1-9]|1[0-9]|2[0-4][0-9]|25[0-5]|1[0-9][0-9]?))', $url)) {
            continue;
        }

        if (preg_match('(172\.(1[6-9]|2[0-9]|3[0-1])\.(0|[1-9]|1[0-9]|2[0-4][0-9]|25[0-5]|1[0-9][0-9]?)\.(0|[1-9]|1[0-9]|2[0-4][0-9]|25[0-5]|1[0-9][0-9]?))', $url)) {
            continue;
        }

        if (preg_match('(127.0.0.1)', $url)) {
            continue;
        }

        $msgSet = [];
        Event::fire('taylor::privmsg: urlDetection', array($url, &$msgSet));

        if (count($msgSet)) {
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
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
        'title'  => 'Image Found: '.$return,
    ];
    return false;
});


// detect imgur links
Event::listen('taylor::privmsg: urlDetection', function ($url, &$msgSet) {
    if (!strpos($url, 'imgur.com')) {
        return;
    }

    // setup a new client
    $client = new GuzzleHttp\Client([
        'base_url' => ['https://api.imgur.com/{version}/', ['version' => '3']],
        'defaults' => [
            'headers' => [
                'Authorization' => 'Client-ID '.Config::get('taylor::api.imgur.client_id', null),
            ]
        ],
        'timeout' => 2,
    ]);

    // figure out which api endpoint to hit
    $apiUrl = null;
    $request = false;
    $type = null;
    switch (true) {
        case strpos($url, '/a/'):
            $type = 'gallery';
            $apiUrl = sprintf('gallery/album/%s', last(explode('/', $url)));
        break;

        case strpos($url, '/gallery/'):
            $type = 'gallery';
            try {
                $request = $client->get(sprintf('gallery/album/%s', last(explode('/', $url))));
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                try {
                    $request = $client->get(sprintf('gallery/image/%s', last(explode('/', $url))));
                    $type = 'image';
                } catch (\GuzzleHttp\Exception\ClientException $e) {
                    $msgSet = [
                        'mode'   => 'imgur',
                        'title'  => '[ Imgur - '.$e->getMessage().' ]',
                    ];
                    return;
                } catch (GuzzleHttp\Exception\ServerException $e) {
                    return;
                } catch (Exception $e) {
                    return;
                }
            } catch (GuzzleHttp\Exception\ServerException $e) {
                return;
            } catch (Exception $e) {
                return;
            }
        break;

        case strpos($url, 'i.imgur.com/'):
        case strpos($url, 'imgur.com/'):
            $type = 'image';
            $filename = last(explode('/', $url));
            $file = head(explode('.', $filename));
            $apiUrl = sprintf('image/%s', $file);
        break;
    }

    // if empty $apiUrl && $request, do nothing
    if (empty($apiUrl) && $request === false) {
        return;
    }

    // if $request is still false, try and grab something
    if ($request === false) {
        try {
            $request = $client->get($apiUrl);
        } catch (GuzzleHttp\Exception\ClientException $e) {
            $msgSet = [
                'mode'   => 'imgur',
                'title'  => '[ Imgur - '.$e->getMessage().' ]',
            ];
            return;
        } catch (GuzzleHttp\Exception\ServerException $e) {
            return;
        } catch (Exception $e) {
            return;
        }
    }

    // make sure status code is right
    if ($request->getStatusCode() != '200') {
        return;
    }

    // grab the json
    $json = json_decode($request->getBody(true), true);
    if (!count($json)) {
        return;
    }

    // output the  info
    $return = null;
    switch($type) {
        case 'gallery':
            $return = sprintf(
                '%s (Views: %d, Image Count: %d, Posted: %s',
                array_get($json, 'data.title'),
                array_get($json, 'data.views'),
                array_get($json, 'data.images_count'),
                date_difference(time()-array_get($json, 'data.datetime'))
            );
        break;

        case 'image':
            $return = sprintf(
                '%s (Dimensions: %sx%s, Views: %d, Size: %s, Posted: %s',
                array_get($json, 'data.title'),
                array_get($json, 'data.height'),
                array_get($json, 'data.width'),
                array_get($json, 'data.views'),
                getHumanReadableSize(array_get($json, 'data.size', 0)),
                date_difference(time()-array_get($json, 'data.datetime'))
            );
        break;
    }
    if (($animated = array_get($json, 'data.animated', null)) !== null && !empty($animated)) {
        $return .= ', Animated: true';
    }
    $return .= ')';

    if (($nsfw = array_get($json, 'data.nsfw', null)) !== null && !empty($nsfw)) {
        $return .= color(' NSFW', 'red');
    }

    $msgSet = [
        'mode'   => 'imgur',
        'title'  => '[ Imgur - '.$return.' ]',
    ];
    return false;
});

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

    // get title
    $url = 'http://www.youtube.com/watch?v='.$matches[1];
    $request = goutteRequest(goutteClient(), $url, 'get');
    if (($request instanceof Symfony\Component\DomCrawler\Crawler) === false) {
        return;
    }
    $title = getNode($request, '#eow-title', null);

    // get length
    $url = 'https://www.googleapis.com/youtube/v3/videos?' . http_build_query([
        'part' => 'contentDetails',
        'id'   => $matches[1],
        'key'  => Config::get('taylor::api.google.api-key')
    ]);

    // grab the request
    $request = guzzleClient('get', $url);
    if (!is_object($request)) {
        return;
    }

    // make sure we got something
    $results = $request->json();
    if (array_get($results, 'kind') != 'youtube#videoListResponse') {
        return;
    }

    if (!count(array_get($results, 'items'))) {
        return;
    }

    $video = array_get($results, 'items.0');
    if (empty($video) || array_get($video, 'kind') != 'youtube#video') {
        return;
    }

    $length = array_get($video, 'contentDetails.duration', 0);
    $length = (new DateTime('@0'))->add(new DateInterval($length))->format('U');

    $msgSet = [
        'mode'   => 'youtube',
        'title'  => '[ You'.color('Tube', 'red').' - '.implode(' | ', [$title, 'Length: '.secs_to_h($length)]).' ]',
    ];
    return false;
});

// spotify links
Event::listen('taylor::privmsg: urlDetection', function ($url, &$msgSet) {
    if (strpos($url, 'spotify.com/') === false && strpos($url, 'spotify:') === false) {
        return;
    }

    $id = substr($url, -22);

    $client = new GuzzleHttp\Client([
        'timeout'  => 2,
    ]);

    $request = guzzleClient('get', sprintf('https://api.spotify.com/v1/tracks/%s', $id));
    if (!is_object($request)) {
        return;
    }

    $json = $request->json();
    $return = sprintf(
        '%s (Artist: %s, Album: %s, Explicit: %s, Length: %s)',
        array_get($json, 'name'),
        array_get($json, 'artists.0.name'),
        array_get($json, 'album.name'),
        array_get($json, 'explicit', false) !== true ? 'false' : 'true',
        secs_to_h(array_get($json, 'duration_ms', 1)/1000)
    );

    if (empty($return)) {
        return;
    }

    $msgSet = [
        'mode'   => 'spotify',
        'title'  => '[ Spotify - '.$return.' ]',
    ];
    return false;
});

// detect imdb links
Event::listen('taylor::privmsg: urlDetection', function ($url, &$msgSet) {
    return;
    if (!strpos($url, 'imdb.com')) {
        return;
    }

    $request = goutteRequest(goutteClient(), $url, 'get');
    if (($request instanceof Symfony\Component\DomCrawler\Crawler) === false) {
        return;
    }

    $parts = [
        'title'          => getNode($request, '#overview-top h1.header .itemprop[itemprop=name]', null),
        'year'           => str_replace(['(', ')'], '', getNode($request, '#overview-top h1.header .nobr', null)),
        'length'         => secs_to_h(Carbon\Carbon::parse(getNode($request, '.infobar time[itemprop=duration]', null))->diffInSeconds()),
        'content-rating' => $request->filter('.infobar span[itemprop=contentRating]')->attr('title') ?: null,
        'rating'         => getNode($request, '.star-box span[itemprop=ratingValue]', 0).'/10',
        'age'            => date_difference(Carbon\Carbon::parse($request->filter('.infobar meta[itemprop=datePublished]')->attr('content'))->diffInSeconds()) ?: null,
        'reviews'        => inBetween('See all ', ' user reviews', $request->filter('.user-comments .see-more a:last-child')->text()) ?: null,
    ];

    $return = null;

    $return = sprintf(
        '%s (Year: %d, Length: %s, Content Rating: %s, Public Rating: %.1f/10, Age: %s, User Reviews: %d)',
        array_get($parts, 'title'),
        array_get($parts, 'year'),
        array_get($parts, 'length'),
        array_get($parts, 'content-rating'),
        array_get($parts, 'rating'),
        array_get($parts, 'age'),
        array_get($parts, 'reviews')
    );


    $msgSet = [
        'mode'   => 'imdb',
        'title'  => '[ IMDB - '.$return.' ]',
    ];
    return false;
});

// detect github links
Event::listen('taylor::privmsg: urlDetection', function ($url, &$msgSet) {
    if (!strpos($url, 'github.com/') !== false) {
        return;
    }

    $request = goutteRequest(goutteClient(), $url, 'get');
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
                    '%s%s: %s by %s (State: %s, Comments: %s)',
                    getNode($request, 'h1.entry-title.public', null),
                    getNode($request, 'h1.gh-header-title span.gh-header-number', 0),
                    getNode($request, 'h1.gh-header-title span.js-issue-title', null),
                    getNode($request, 'a.author', 0),
                    getNode($request, 'div.gh-header-meta .state', 0),
                    inBetween('· ', ' comment', getNode($request, '.flex-table-item.flex-table-item-primary', null))
                ));
            }
            break;

        // https://github.com/<username>/<repo>/pull/<pull_id>
        case (strpos($url, '/pull/') !== false):
            $return = strip_whitespace(sprintf(
                '%s%s: %s (State: %s, Comments: %s, Commits: %s, Files Changed: %s)',
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
                '%s: (Starred: %s, Repo Forks: %s, Commits: %s, Contributors: %s)',
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
                    'Github Organisation: %s / People: %s',
                    getNode($request, 'span.js-username', null),
                    getNode($request, 'span.org-stats', 0)
                );

            //else profile!
            } else {
                $return = sprintf(
                    'Github User: %s (Followers: %s, Starred: %s, Following: %s, Year of Contributions: %s, Joined: %s)',
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
});


// normal links
Event::listen('taylor::privmsg: urlDetection', function ($url, &$msgSet) {

    $request = goutteRequest(goutteClient(), $url, 'get');
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
            'title' => 'URL Found: '.\Str::limit($title, 240),
        ];
        return false;
    }

    return;
});
