<?php

namespace AdrHumphreys\Impetuous;

use AdrHumphreys\Impetuous\Services\Cache;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\ORM\DataExtension;

/**
 * Provides cache clearing on publish
 *
 * @property SiteTree $owner
 */
class SiteTreeExtension extends DataExtension
{
    /*
     * Clear the cache for urls of the page before it's been published.
     * This allows us to clear the original URLs of the page rather than the new URLs
     * incase a user has changed them
     */
    public function onBeforePublish(SiteTree $original): void
    {
        $this->clearURLs($original);
    }

    /*
     * Clear the cache for the extended page when it's unpublished
     */
    public function onBeforeUnpublish(): void
    {
        $this->clearURLs();
    }

    /*
     * Function to clear the urls passed through from `getURLs` on the extended object.
     * Optionally pass through the "original" version of the extended object to clear it's
     * URLs to.
     */
    private function clearURLs(?SiteTree $original = null): void
    {
        if ($original !== null) {
            foreach ($original->getURLs() as $URL) {
                Cache::clear($URL);
            }
        }

        foreach ($this->owner->getURLs() as $URL) {
            Cache::clear($URL);
        }
    }

    /*
     * This can be overridden on pages where you'd prefer to not cache the pages URL
     * or where it has specific URLs that don't match what the SiteTree expects
     */
    public function getURLs(): array
    {
        return [$this->owner->Link()];
    }
}
