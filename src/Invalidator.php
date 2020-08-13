<?php

namespace AdrHumphreys\Impetuous;

use SilverStripe\Core\Injector\Injectable;

/*
 * Inject over this class to handle cache invalidation in your CDN or other additional
 * actions that are required when invalidating the cache
 */
class Invalidator
{
    use Injectable;

    /*
     * This will be called when a single URL has going to be invalidated
     */
    public function invalidate(string $url): void
    {
        // Cache invalidation via CDN would be done here
    }

    /*
     * This will be called when the entire cache has going to be invalidated
     */
    public function invalidateAll(): void
    {
        // Cache invalidation for ALL records via CDN would be done here
    }
}
