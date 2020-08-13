<?php

namespace AdrHumphreys\Impetuous;

use AdrHumphreys\Impetuous\Services\Cache;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Middleware\HTTPMiddleware;

/*
 * Middleware that processes a request and then will try to cache the response
 * before returning it.
 */
class CacheResponse implements HTTPMiddleware
{
    public function process(HTTPRequest $request, callable $delegate)
    {
        $response = $delegate($request);

        Cache::cacheResponse($request, $response);

        return $response;
    }
}
