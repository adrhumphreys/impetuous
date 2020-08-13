<?php

namespace AdrHumphreys\Impetuous;

use AdrHumphreys\Impetuous\Services\Cache;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\BuildTask;

/*
 * This is used to provide a method of clearing the cache during development
 * I would suggest implementing your own clearing strategy or using the default
 */
class Clear extends BuildTask
{
    /**
     * @param HTTPRequest|mixed $request
     */
    public function run($request): void
    {
        if ($url = $request->getVar('url')) {
            Cache::clear($url);
            echo 'Cleared `' . $url . '` 🌊';
        } else {
            Cache::clearAll();
            echo 'Cleared all 🌊';
        }
    }
}
