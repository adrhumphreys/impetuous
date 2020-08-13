<?php

namespace AdrHumphreys\Impetuous;

use AdrHumphreys\Impetuous\Services\Cache;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\BuildTask;

/*
 * This will clear all the URLs that have expired. If you've changed the passed through
 * cache lifetime you'll need to run the task with `duration` changed to the same period
 */
class ClearExpiredRecords extends BuildTask
{
    /**
     * @param HTTPRequest|mixed $request
     */
    public function run($request): void
    {
        $duration = $request->getVar('duration') ?? 86400;
        $records = CachedRecord::get()->filter('Created:LessThan', strtotime('-' . $duration));
        /** @var CachedRecord $record */
        foreach ($records as $record) {
            Cache::clear($record->URL);
        }
    }
}
