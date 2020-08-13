<?php

namespace AdrHumphreys\Impetuous;

use AdrHumphreys\Impetuous\Services\Cache;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\BuildTask;

/*
 * This is used to provide a method of warming the cache during development
 * I would suggest implementing your own warming strategy
 */
class Warm extends BuildTask
{
    /**
     * @param HTTPRequest|mixed $request
     */
    public function run($request): void
    {

        $urlInput = $request->getVar('urls');

        if ($urlInput) {
            $urls = explode(PHP_EOL, $urlInput);
            Cache::warmURLs($urls);

            echo 'Cached :' . PHP_EOL;

            array_walk($urls, function($url) {
                echo $url . PHP_EOL;
            });

            return;
        }

        echo <<<HTML
<p>Urls should look like `about-us` excluding the domain</p>
<form method="get">
<textarea rows="10" cols="80" name="urls"></textarea>
<br>
<button>Cache URLs</button>
</form>
HTML;
    }
}
