<?php

namespace App\Models\Concerns;

use App\Support\CatalogCache;

/** Any change to a model that appears on public Catalog pages invalidates the Catalog cache. */
trait BumpsCatalogVersion
{
    protected static function bootBumpsCatalogVersion(): void
    {
        static::saved(fn () => CatalogCache::bump());
        static::deleted(fn () => CatalogCache::bump());
    }
}
