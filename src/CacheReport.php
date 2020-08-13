<?php

namespace AdrHumphreys\Impetuous;

use SilverStripe\ORM\DataList;
use SilverStripe\Reports\Report;

/*
 * Used to show a report which lists all cached records
 */
class CacheReport extends Report
{
    protected $title = 'Cache report';
    protected $description = 'Check out how the cache is performing';

    public function sourceRecords($params = [], $sort = null, $limit = null): DataList
    {
        return CachedRecord::get();
    }
}
