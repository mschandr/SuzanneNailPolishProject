<?php

namespace App\Models\Concerns;

use App\Support\CatalogCache;

/** Any change to a model that appears on public Catalog pages invalidates the Catalog cache. */
trait BumpsCatalogVersion
{
    protected static function bootBumpsCatalogVersion(): void
    {
        // A User's Private Polish must not invalidate the public cache for everyone.
        $affects = fn ($model) => ! method_exists($model, 'affectsPublicCatalog') || $model->affectsPublicCatalog();

        static::saved(fn ($model) => $affects($model) && CatalogCache::bump());
        static::deleted(fn ($model) => $affects($model) && CatalogCache::bump());
    }
}
