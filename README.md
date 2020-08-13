# Impetuous - Static caching

Static caching of pages made easy.
This will cache 200 GET responses and store them by default in `public/cache`
It will cache these files on request so you don't need any pesky queues.
By default, a cached file will last for 1 day then it will be bypassed. When bypassed if the route is still returning a 200 then it will be re-cached and that cache will start to be served again (documented below to change).

## What does it not cover?
- You'll need to implement an invalidation strategy (for the CDN, documented below)
- You'll need to implement `onAfterWrite/onAfterPublish/onAfterUnpublish/onAfterDelete` on `DataObjects` to clear the cache for their linked pages (this will already do that for pages on publish, for other items it's documented below)
- You'll need to prevent caching specific requests for marketing tags such as `cid` (documented below)
- You'll need to decide on a recaching strategy (by default it's on publish of the page and invalidated after a day)
- You'll need to decide on your cache headers (documented below)
- Headers, these are not planned for yet

## So what does it cover?
- Caching a request during the request lifecycle (this means you won't cache unvisited pages)
- Methods to hook into for invalidating via a CDN
- Functionality to serve the cached files
- Functionality to store the cached files along with database records
- A report on the cached responses
- Filtering to prevent caching incorrect responses
- The ability to programmatically warm the cache
- Automatic re-cache of pages after being cached for a period (defaults to 1 day)

## But how does it scale?
Good question, this depends on the definition of scale. Cache serving scales up without much worry. The ability to re-cache the entire site is a tricky answer. If most requests to the site are to new uncached pages then you'll need to implement some prewarming strategy. If most of the pages serve the same content then you should be fine but you should look at implementing a whitelist for cacheable query params. Especially if using marketing tags.

## Requirements

* SilverStripe ^4.0
* A hankering for risk

## Installation
```
composer require adrhumphreys/impetuous dev-master
```

Edit your `public/index.php` file to add the following before the autoloader from composer kicks in:
```php
$requestHandler = require '../vendor/adrhumphreys/impetuous/includes/impetuous.php';

if (false !== $requestHandler('cache')) {
    die;
} else {
    header('X-Cache-Miss: ' . date(DateTime::COOKIE));
}
```

By default, the cache time for a file is 1 day to change this edit the line about to the time in seconds you want the cache to last. E.g. `$requestHandler('cache', 60)` will set the cache time to 60 seconds.

You'll need to ensure that the middleware `CacheResponse` is applied as the last middleware in the stack

### Environment values:
`IMPETUOUS_CACHE_DIR`: This is where the cache is stored, defaults to `cache`
`IMPETUOUS_RECORD`: Determines if we will record the cache in the database too

## Prevent caching specific routes:
You'll want to inject over `Cache` as a subclass and update `shouldCache` to return `false` for routes that shouldn't be cached. As an example if you wanted to never cache query params it would look like:
```php
<?php

namespace App;

use AdrHumphreys\Impetuous\Cache;

class CustomCache extends Cache {
    protected static function shouldCache(HTTPRequest $request, HTTPResponse $response): bool
    {
        // Don't cache query params
        if (count($request->getVars()) > 0) {
            return false;
        }

        return parent::shouldCache($request, $response);
    }
}
```

## Controlling cache headers:
This has been left up to the developer. You'll need to weight the pros and cons of different methods.
An example would be to set the `Cache-Control` headers to have a large max age and rely on CDN invalidation (mentioned below)
That would look something like:
```php
$requestHandler = require '../vendor/adrhumphreys/impetuous/includes/impetuous.php';

if (false !== $requestHandler('cache')) {
    header('Cache-Control: public, maxage=3600');
    die;
}
```

## Invalidation via CDN:
You'll need to inject over `Invalidator` and handle both `invalidate` and `invaldidateAll`.
When a cache entry is invalidated, `invalidate` will be passed the URL of the entry, and you can then invalidate the URL in your chosen CDN.

## Recache when linked items change:
This has been left to the developer to implement, I've outlined a basic example below that you can follow if needed.
```php
<?php

namespace App;

class MyObject extends DataObject
{
    private static $has_one = ['LinkedPage' => SiteTree::class];

    public function onAfterWrite()
    {
        parent::onAfterWrite();
        Cache::clear($this->LinkedPage()->Link());
    }

    public function onBeforeDelete()
    {
        parent::onBeforeDelete();
        Cache::clear($this->LinkedPage()->Link());
    }
}
```

## Warming the cache:
You can call `Cache::warmURLs($urls)` where `$urls` are and array of urls.
You can manually set up a task to warm specific URLs.

## Included tasks:
- Clear: To be used to clear specific cache records, this will likely only be useful during development and testing.
- Warm: To be used to warm specific URLs, again for development/testing.
- ClearExpiredRecords: This would ideally be run as a cron task, it deletes records that no longer will be served due to them expiring.

## Included reports:
- Cached report: This will show a list of the cached URLs, the file location and when they were cached

## Trying for better performance
For URLs without any query params you can try to respond from the file cache through Apache/Nginx.
Some example configs are below for you to try.
For requests with query params we need to fallback to PHP to decipher the query param into a filename.

This will handle query params then you can edit your `.htaccess` or `.conf` for the other roues like so:
`.htaccess`:
```
## CONFIG FOR STATIC PUBLISHING
# Cached content - sub-pages (site in the root of a domain)
RewriteCond %{REQUEST_METHOD} ^GET|HEAD$
RewriteCond %{QUERY_STRING} ^$
RewriteCond %{REQUEST_URI} /(.*[^/])/?$
RewriteCond %{DOCUMENT_ROOT}/cache/%1.html -f
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule .* /cache/%1.html [L]

# Cached content - homepage (site in root of a domain)
RewriteCond %{REQUEST_METHOD} ^GET|HEAD$
RewriteCond %{QUERY_STRING} ^$
RewriteCond %{REQUEST_URI} ^/?$
RewriteCond %{DOCUMENT_ROOT}/cache/index.html -f
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule .* /cache/index.html [L]
```

`.conf`:
```
# Otherwise, see if the file exists and serve if so, pass through if not (but regexs below take precedence over this)
# This checks if the static cache has been built, and server that static html.
# Order is the php copy, then the html copy, an actual file somewhere and at last pass it down to the webserver
location / {
        try_files /cache/$request_uri.php /cache/$request_uri.html $uri @passthrough;
        open_file_cache max=1000 inactive=120s;
        open_file_cache_valid 5;
        expires 5;
}

# PHP request, always pass to apache
location ~* \.php$ {
        error_page 404 = @passthrough;
        return 404;
}

# This is the backend webserver that is defined in the main nginx.conf
location @passthrough {
        proxy_pass http://backend;
        expires off;
}
```

## License
I made this? You made this! Use it for anything aside from making fun of my coding ability
