<?php

namespace AdrHumphreys\Impetuous\Includes;

if (!function_exists('AdrHumphreys\\Impetuous\\Includes\\urlToPath')) {
    /**
     * @param string $url
     * @param string $baseURL
     * @return string|null either the path to store the file or null if it's not possible
     */
    function urlToPath(string $url, string $baseURL = ''): ?string
    {
        // parse_url() is not multibyte safe, see https://bugs.php.net/bug.php?id=52923.
        // We assume that the URL hsa been correctly encoded either on storage (for SiteTree->URLSegment),
        // or through URL collection (for controller method names etc.).
        $urlParts = @parse_url($url);
        $suffix = '';

        if ($urlParts['query'] ?? false) {
            // Parse our GET vars.
            parse_str($urlParts['query'], $getVars);

            // Find out if any of our GET vars are in our black list.
            $blacklistIntersection = array_intersect([
                'stage',
                'CMSPreview',
                'flush',
                'showtemplate',
                'isDev',
                'isTest',
                'debug',
                'debug_request',
                'debugfailover',
                'showqueries',
                'previewwrite',
                'BackURL',
                'requireLinkText',
                'Version',
                'ids',
                'ParentID',
                'view',
                'locale',
                'group_key',
            ], array_keys($getVars));

            // If there are, we should bypass static cache.
            if (count($blacklistIntersection) > 0) {
                return null;
            }

            foreach ($getVars as $param => $var) {
                $suffix .= sprintf('.%s-%s', $param, $var);
            }
        }

        // Remove base folders from the URL if webroot is hosted in a subfolder)
        $path = isset($urlParts['path'])
            ? urldecode($urlParts['path'])
            : '';

        $urlSegment = mb_substr(mb_strtolower($path), 0, mb_strlen($baseURL)) == mb_strtolower($baseURL)
            ? mb_substr($path, mb_strlen($baseURL))
            : $path;

        // Normalize URLs
        $urlSegment = trim($urlSegment, '/');

        // Default to index for the root URL
        $filename = $urlSegment ?: 'index';

        $dirName = dirname($filename);
        $prefix = '';

        if ($dirName != '/' && $dirName != '.') {
            $prefix = $dirName . '/';
        }

        return $prefix . basename($filename) . $suffix . '.html';
    }
}


if (!function_exists('AdrHumphreys\\Impetuous\\Includes\\pathToURL')) {
    /**
     * Used to convert a path to the source URL of the request
     *
     * @param string $path the path to the file e.g. `cache/about/contact.html`
     * @param string $destPath the directory where the cache is stored e.g. `cache`
     * @return string the url matey
     */
    function pathToURL(string $path, string $destPath): string
    {
        // Strip off the full path of the cache dir from the front
        if (strpos($path, $destPath) === 0) {
            $path = substr($path, strlen($destPath));
        }

        // Strip off the file extension and leading /
        $relativeURL = substr($path, 0, strrpos($path, '.'));
        $relativeURL = ltrim($relativeURL, '/');

        // Parse suffixed GET parameters
        $segments = explode('.', $relativeURL);

        // In this case the only dot is for html
        if (count($segments) < 2) {
            return $relativeURL;
        }

        // The first segment is the URL, all segments after it are the query params
        $relativeURL = $segments[0];
        $parameters = [];

        // Convert each segment `queryParam-value` into `queryParam=value`
        foreach (array_slice($segments, 1) as $param) {
            [$key, $value] = array_pad(explode('-', $param), 2, '');

            $parameters[] = $value
                ? sprintf('%s=%s', $key, $value)
                : $key;
        }

        return $relativeURL . '?' . implode('&', $parameters);
    }
}
