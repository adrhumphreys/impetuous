<?php

namespace AdrHumphreys\Impetuous;

use SilverStripe\ORM\DataObject;

/**
 * Used to store a reference to a cached response
 *
 * @property string $Expiry
 * @property string $FilePath
 * @property string $Hash
 * @property string $URL
 */
class CachedRecord extends DataObject
{
    /**
     * @var string
     */
    private static $table_name = 'Impetuous_CachedRecord';

    /**
     * @var string[]
     */
    private static $db = [
        'Expiry' => 'DBDatetime',
        'FilePath' => 'Varchar(255)',
        'Hash' => 'Varchar(32)',
        'URL' => 'Varchar(255)',
    ];

    /**
     * @var string[]
     */
    private static $summary_fields = [
        'Created' => 'Created',
        'URL' => 'URL',
        'FilePath' => 'File Location',
    ];

    /**
     * @var array
     */
    private static $indexes = [
        'UniqueURL' => [
            'type' => 'unique',
            'columns' => [ 'Hash' ],
        ],
    ];

    public function onBeforeWrite(): void
    {
        parent::onBeforeWrite();

        $this->Hash = md5($this->FilePath);
    }

    /*
     * Helper to get a cached record by path
     */
    public static function getByPath(string $path): ?CachedRecord
    {
        return CachedRecord::get()
            ->filter('Hash', md5($path))
            ->first();
    }
}
