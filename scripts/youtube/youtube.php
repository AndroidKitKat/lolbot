<?php

use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;

$youtube_history = [];
function youtube(\Irc\Client $bot, $chan, $text)
{
    global $config, $youtube_history;
    $key = $config['gkey'];
    $URL = '/^((?:https?:)?\/\/)?((?:www|m)\.)?((?:youtube\.com|youtu.be))(\/(?:[\w\-]+\?v=|embed\/|v\/)?)([\w\-]+)(\S+)?$/';
    foreach (explode(' ', $text) as $word) {
        if (!preg_match($URL, $word, $m)) {
            continue;
        }

        if (!array_key_exists(5, $m)) {
            continue;
        }

        $id = $m[5];
        // Get this with https://www.youtube.com/watch?time_continue=165&v=Bfdy5a_R4K4
        if ($id == "watch") {
            $url = parse_url($word, PHP_URL_QUERY);
            foreach (explode('&', $url) as $p) {
                list($lhs, $rhs) = explode('=', $p);
                if ($lhs == 'v') {
                    $id = $rhs;
                }
            }
        }

        if($youtube_history[$chan] ?? false == $id) {
            echo "Ignoring repeated link of $id\n";
            return;
        }
        $youtube_history[$chan] = $id;
        echo "Looking up youtube video $id\n";

        $data = null;
        $body = null;
        try {
            $client = HttpClientBuilder::buildDefault();
            /** @var Response $response */
            $response = yield $client->request(new Request("https://www.googleapis.com/youtube/v3/videos?id=$id&part=snippet%2CcontentDetails%2Cstatistics&key=$key"));
            $body = yield $response->getBody()->buffer();
            if ($response->getStatus() != 200) {
                $bot->pm($chan, "Error (" . $response->getStatus() . ")");
                echo "Error (" . $response->getStatus() . ")\n";
                var_dump($body);
                return;
            }
            $data = json_decode($body, false);
        } catch (HttpException $error) {
            // If something goes wrong Amp will throw the exception where the promise was yielded.
            // The HttpClient::request() method itself will never throw directly, but returns a promise.
            echo "$error\n";
            $bot->pm($chan, "\2YouTube Error:\2 " . $error);
        }

        if (!is_object($data)) {
            echo "No data\n";
            var_dump($data);
            continue;
        }
        try {
            $v = $data->items[0];
            $title = $v->snippet->title;

            $di = new DateInterval($v->contentDetails->duration);
            $dur = '';
            if($di->s > 0) {
                $dur = "{$di->s}s";
            }
            if ($di->i > 0) {
                $dur = "{$di->i}m $dur";
            }
            if ($di->h > 0) {
                $dur = "{$di->h}h $dur";
            }
            if ($di->d > 0) {
                $dur = "{$di->d}d $dur";
            }
            //Seems unlikely, months and years
            if ($di->m > 0) {
                $dur = "{$di->m}M $dur";
            }
            if ($di->y > 0) {
                $dur = "{$di->y}y $dur";
            }
            $dur = trim($dur);
            if($dur == '') {
                $dur = 'LIVE';
            }
            $chanTitle = $v->snippet->channelTitle;
            $datef = 'M j, Y';
            $date = date($datef, strtotime($v->snippet->publishedAt));
            $views = number_format($v->statistics->viewCount);
            $likes = number_format($v->statistics->likeCount);
            $hates = number_format($v->statistics->dislikeCount);

            if($config['youtube_thumb'] ?? false && isset($config['p2u'])) {
                $thumbnail = $v->snippet->thumbnails->high->url;
                $ext = explode('.', $thumbnail);
                $ext = array_pop($ext);
                try {
                    echo "fetching thumbnail at $thumbnail\n";
                    $client = HttpClientBuilder::buildDefault();
                    /** @var Response $response */
                    $response = yield $client->request(new Request($thumbnail));
                    $body = yield $response->getBody()->buffer();
                    if ($response->getStatus() != 200) {
                        $bot->pm($chan, "Error (" . $response->getStatus() . ")");
                        echo "Error (" . $response->getStatus() . ")\n";
                        var_dump($body);
                    } else {
                        $filename = "thumb_$id.$ext";
                        echo "saving to $filename\n";
                        file_put_contents($filename, $body);
                        $width = $config['youtube_thumbwidth'] ?? 40;
                        $filename_safe = escapeshellarg($filename);
                        $thumbnail = `$config[p2u] -f m -p x -w $width $filename_safe`;
                        unlink($filename);
                    }
                } catch (HttpException $error) {
                    // If something goes wrong Amp will throw the exception where the promise was yielded.
                    // The HttpClient::request() method itself will never throw directly, but returns a promise.
                    echo "$error\n";
                    $thumbnail = '';
                }
                if ($thumbnail != '') {
                    $thumbnail = explode("\n", trim($thumbnail));
                    foreach([count($thumbnail)-1,count($thumbnail)-2,1,0] as $i) {
                        if (trim($thumbnail[$i]) == "\x031,1") {
                            unset($thumbnail[$i]);
                        }
                    }
                    foreach ($thumbnail as $line) {
                        $bot->pm($chan, $line);
                    }
                }
            }

            $bot->pm($chan, "\2\3" . "01,00You" . "\3" . "00,04Tube\3\2 $title | $chanTitle | $dur");
        } catch (Exception $e) {
            $bot->pm($chan, "\2YouTube Error:\2 Unknown data received.");
            echo "\2YouTube Error:\2 Unknown data received.\n";
            var_dump($body);
        }
    }
}