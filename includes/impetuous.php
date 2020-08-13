<?php

namespace AdrHumphreys\Impetuous\Includes;

require_once 'urlToPath.php';

/*
 * By default the cache lifetime is set to 1 day, you can pass 0 through to never
 * invalidate the cached file.
 */
return function(?string $cacheDir, int $cacheLifetimeInSeconds = 86400): bool
{
    // If this cookie is set we'll bypass the cache. This can be used to bypass
    // the cache when pre-warming it.
    if (isset($_COOKIE['bypassStaticCache'])) {
        return false;
    }

    // Convert into a full URL
    $port = isset($_SERVER['SERVER_PORT']) ? $_SERVER['SERVER_PORT'] : 80;
    $https = $port === '443' || isset($_SERVER['HTTPS']) || isset($_SERVER['HTTPS']);
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';

    $url = $https ? 'https://' : 'http://';
    $url .= $host . $uri;

    $path = urlToPath($url);

    if (!$path) {
        return false;
    }

    $cachePath = $cacheDir . DIRECTORY_SEPARATOR . $path;

    // Check for directory traversal attack
    $realCacheDir = realpath($cacheDir);
    $realCachePath = realpath($dirname = dirname($cachePath));

    // Path is outside the cache dir
    if (substr($realCachePath, 0, strlen($realCacheDir)) !== $realCacheDir) {
        return false;
    }

    if(!file_exists($cachePath)) {
        if ($path !== 'index.html') {
            return false;
        }

        // The homepage is a special case in which it can be stored as home.html
        // rather than index.html
        $cachePath = str_replace('index.html', 'home.html', $cachePath);

        if (!file_exists($cachePath)) {
            return false;
        }
    }

    // Only serve files that have last been edited within the cache lifetime
    if ($cacheLifetimeInSeconds > 0) {
        $lastModified = @filemtime($cachePath);
        if (!$lastModified || (time() - $lastModified >= $cacheLifetimeInSeconds)){
            return false;
        }
    }

    // Add a header for hitting the cache
    header('X-Cache-Hit: ' . date(\DateTime::COOKIE));

    // Process ETags
    $etag = '"' . md5_file($cachePath) . '"';
    if (!empty($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] == $etag) {
        header('HTTP/1.1 304', true);
        return true;
    }
    header('ETag: ' . $etag);

    readfile($cachePath);

    return true;
};
