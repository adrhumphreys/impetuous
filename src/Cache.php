<?php

namespace AdrHumphreys\Impetuous\Services;

use AdrHumphreys\Impetuous\CachedRecord;
use AdrHumphreys\Impetuous\Invalidator;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use SilverStripe\Assets\Filesystem;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DB;
use SilverStripe\Security\Security;
use function AdrHumphreys\Impetuous\Includes\pathToURL;
use function AdrHumphreys\Impetuous\Includes\urlToPath;

/*
 * This is the main functionality of the module, provides the ability to cache responses,
 * clear cached responses, and warm the cache
 */
class Cache
{
    use Injectable;

    // Env var set to decide if you record the cache in the database
    private const IMPETUOUS_RECORD = 'IMPETUOUS_RECORD';
    // Default directory to store the cache
    private const DEFAULT_CACHE_DIR = 'cache';
    // Override the default directory with your own via an env var
    private const IMPETUOUS_CACHE_DIR = 'IMPETUOUS_CACHE_DIR';

    /*
     * This is where the cached files are stored
     * Can be changed by setting the env var `IMPETUOUS_CACHE_DIR` to your chosen directory.
     */
    public static function getStoragePath(): string
    {
        $path = self::DEFAULT_CACHE_DIR;

        // Hard to trust users will set this correctly sometimes
        $setPath = Environment::getEnv(self::IMPETUOUS_CACHE_DIR);
        if ($setPath !== null && $setPath !== '' && $setPath !== false) {
            $path = $setPath;
        }

        // When in CLI mode the path gets changed from /public to / so
        // we need to account for that
        if (Director::is_cli()) {
            $path = PUBLIC_DIR . DIRECTORY_SEPARATOR . $path;
        }

        return $path;
    }

    /*
     * This takes in the context of a request and the response generated from that request
     * to decide if with that context we should record a cache.
     * This prevents us from caching logged in users, 404s, posts, etc
     *
     * To change this functionality you should inject over the class
     */
    protected static function shouldCache(HTTPRequest $request, HTTPResponse $response): bool
    {
        // Only cache GET requests
        if ($request->httpMethod() !== 'GET') {
            return false;
        }

        // Don't cache unsuccessful responses
        if ($response->getStatusCode() !== 200) {
            return false;
        }

        // Don't cache logged in users
        if (Security::getCurrentUser() !== null) {
            return false;
        }

        // Don't cache CLI tasks and requests
        if (Environment::isCli()) {
            return false;
        }

        // Don't cache forgotten password, login, etc pages
        if (strpos($request->getURL(false), 'Security') === 0) {
            return false;
        }

        // Don't cache flushing
        if ($request->getVar('flush')) {
            return false;
        }

        // Don't cache staged pages
        if ($request->getVar('stage')) {
            return false;
        }

        // Don't cache empty responses
        if (empty($response->getBody())) {
            return false;
        }

        return true;
    }

    /*
     * This takes in the context of a request and the response generated from that request.
     * It uses that context to cache the response against the requested URL into a file on disk
     */
    public static function cacheResponse(HTTPRequest $request, HTTPResponse $response): void
    {
        $cacheDir = self::getStoragePath();

        if (self::singleton()->shouldCache($request, $response) === false) {
            return;
        }

        $requestedURL = $request->getURL(true);
        $fileName = urlToPath($requestedURL, BASE_URL);
        $path = $cacheDir . DIRECTORY_SEPARATOR . $fileName;

        // In some cases unlocked files can be read during the write so we
        // write to a temporary file before moving that file to prevent that
        $temporaryPath = tempnam(TEMP_PATH, 'filesystempublisher_');
        if (file_put_contents($temporaryPath, $response->getBody()) === false) {
            // Due to the nature of this being additive, we don't throw execeptions
            // but just don't cache the response
            return;
        }

        Filesystem::makeFolder(dirname($path));

        // If something goes wrong with the move we'll exit early rather than
        // record that we've cached the response (as we haven't actually cached it)
        if (!rename($temporaryPath, $path)) {
            return;
        }

        // Invalidate the URL as the content has been updated. This can
        // then be used to send a request to invalidate the URL in the CDN
        Invalidator::singleton()->invalidate($requestedURL);

        // Now we record the cache in the database
        $shouldRecord = Environment::getEnv(self::IMPETUOUS_RECORD);
        if ($shouldRecord !== true) {
            return;
        }

        $existingRecord = CachedRecord::getByPath($path);

        if ($existingRecord) {
            $existingRecord->write();
            return;
        }

        CachedRecord::create([
            'FilePath' => $path,
            'URL' => $requestedURL,
        ])->write();
    }

    /*
     * This will warm a set of URLs, be careful in the amount of URLS
     * that are being passed through as each of those URLs will result
     * in a request being performed to the server. Too many and the request
     * that is trying to warm the URLs will timeout.
     */
    public static function warmURLs(array $urls): void
    {
        $client = new Client();

        foreach ($urls as $url) {
            $url = Director::absoluteURL($url);
            $client->get($url, [
                'cookies' => CookieJar::fromArray([
                    'bypassStaticCache' => 'true',
                ], BASE_URL)
            ]);
        }
    }

    /*
     * This will clear both the database record and file of a cached response
     * By default we clear the URL and any query params attached to it, so for
     * example if you passed `about-us` through it would clear both `about-us`
     * and `about-us?a=b`.
     */
    public static function clear(string $url, bool $includeVariants = true): void
    {
        $path = urlToPath($url, BASE_URL);
        $deletePath = self::getStoragePath() . DIRECTORY_SEPARATOR . $path;
        $invalidator = Invalidator::singleton();
        $recordInDatabase = Environment::getEnv(self::IMPETUOUS_RECORD) === true;

        if (!$includeVariants) {
            if (file_exists($deletePath)) {
                unlink($deletePath);

                if ($recordInDatabase) {
                    $record = CachedRecord::getByPath($deletePath);

                    if ($record) {
                        $record->delete();
                    }
                }

                $invalidator->invalidate(pathToURL($deletePath, self::getStoragePath()));
            }

            return;
        }

        $searchPath = rtrim($deletePath, '.html') . '*' . '.html';

        foreach (glob($searchPath) as $filename) {
            unlink($filename);

            if ($recordInDatabase) {
                $record = CachedRecord::getByPath($filename);

                if ($record) {
                    $record->delete();
                }
            }

            $invalidator->invalidate(pathToURL($filename, self::getStoragePath()));
        }
    }

    /*
     * This will clear both the database records and files of the cache
     */
    public static function clearAll(): void
    {
        $cacheDir = self::getStoragePath();

        $folderExists = file_exists($cacheDir);

        if ($folderExists) {
            Filesystem::removeFolder($cacheDir);
        }

        $recordInDatabase = Environment::getEnv(self::IMPETUOUS_RECORD) === true;

        if($recordInDatabase) {
            $tableName = Config::inst()->get(CachedRecord::class, 'table_name');
            DB::get_conn()->clearTable($tableName);
        }

        Invalidator::singleton()->invalidateAll();
    }
}
