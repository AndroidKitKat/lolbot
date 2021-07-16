<?php
//TODO code syntax highlighting
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use knivey\cmdr\attributes\CallWrap;
use knivey\cmdr\attributes\Cmd;
use knivey\cmdr\attributes\Options;
use knivey\cmdr\attributes\Syntax;

#[Cmd("url", "img")]
#[Syntax('<input>')]
#[CallWrap("Amp\asyncCall")]
#[Options("--rainbow", "--rnb", "--bsize", "--width")]
function url($args, \Irc\Client $bot, \knivey\cmdr\Request $req) {
    global $config;
    $url = $req->args[0] ?? '';
    if(!filter_var($url, FILTER_VALIDATE_URL)) {
        $bot->pm($args->chan, "invalid url");
        return;
    }

    if(preg_match('/^https?:\/\/pastebin.com\/([^\/]+)$/i', $url, $m)) {
        if(strtolower($m[1]) != 'raw') {
            $url = "https://pastebin.com/raw/$m[1]";
        }
    }
    echo "Fetching URL: $url\n";

    try {
        $client = HttpClientBuilder::buildDefault();
        $request = new Request($url);

        /** @var Response $response */
        $response = yield $client->request($request);
        $body = yield $response->getBody()->buffer();
        if ($response->getStatus() != 200) {
            $body = substr($body, 0, 200);
            $bot->pm($args->chan, "Error (" . $response->getStatus() . ") $body");
            return;
        }

        $type = explode("/", $response->getHeader('content-type'));
        if(!isset($type[0])) {
            $bot->pm($args->chan, "content-type not provided");
            return;
        }
        if($type[0] == 'image') {
            if(!isset($config['p2u'])) {
                $bot->pm($args->chan, "p2u hasn't been configued");
                return;
            }
            $ext = $type[1] ?? 'jpg'; // /shrug
            $filename = "url_thumb.$ext";
            echo "saving to $filename\n";
            file_put_contents($filename, $body);
            $width = ($config['url_default_width'] ?? 55);
            if($req->args->getOptVal("--width") !== false) {
                $width = intval($req->args->getOptVal("--width"));
                if($width < 10 || $width > 200) {
                    $bot->pm($args->chan, "--width should be between 10 and 200");
                    return;
                }
            }
            $filename_safe = escapeshellarg($filename);
            $thumbnail = `$config[p2u] -f m -p x -w $width $filename_safe`;
            unlink($filename);
            $cnt = 0;
            $thumbnail = explode("\n", $thumbnail);
            foreach ($thumbnail as $line) {
                if($line == '')
                    continue;
                $bot->pm($args->chan, $line);
                if($cnt++ > ($config['url_max'] ?? 100)) {
                    $bot->pm($args->chan, "wow thats a pretty big image, omitting ~" . count($thumbnail)-$cnt . "lines ;-(");
                    return;
                }
            }
        }
        if($type[0] == 'text') {
            var_dump($type);
            if(isset($type[1]) && !preg_match("/^plain;?/", $type[1])) {
                $bot->pm($args->chan, "content-type was ".implode('/', $type)." should be text/plain or image/* (pastebin.com maybe works too)");
                return;
            }
            if($req->args->getOpt('--rainbow') || $req->args->getOpt('--rnb')) {
                $dir = $req->args->getOptVal('--rainbow');
                if($dir === false)
                    $dir = $req->args->getOptVal('--rnb');
                $dir = intval($dir);
                $bsize = $req->args->getOptVal('--bsize');
                if(!$bsize)
                    $bsize = null;
                else
                    $bsize = intval($bsize);
                $body = irctools\diagRainbow($body, $bsize, $dir);
            }
            $cnt = 0;
            $body = explode("\n", $body);
            foreach ($body as $line) {
                if($line == '')
                    continue;
                $bot->pm($args->chan, $line);
                if($cnt++ > ($config['url_max'] ?? 100)) {
                    $bot->pm($args->chan, "wow thats a pretty big text, omitting ~" . count($body)-$cnt . "lines ;-(");
                    return;
                }
            }
        }

    } catch (\Amp\MultiReasonException $errors) {
        foreach ($errors->getReasons() as $error) {
            echo $error;
            $bot->pm($args->chan, "\2URL Error:\2 " . substr($error, 0, strpos($error, "\n")));
        }
    } catch (\Exception $error) {
        // If something goes wrong Amp will throw the exception where the promise was yielded.
        // The HttpClient::request() method itself will never throw directly, but returns a promise.
        echo $error;
        $bot->pm($args->chan, "\2URL Error:\2 " . substr($error, 0, strpos($error, "\n")));
    }
}
