<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Caches public Catalog reads. Every key includes a version number; any Catalog
 * edit bumps it, so all earlier entries become unreachable at once. This works
 * on any cache driver (no tags needed) and old entries simply expire.
 */
class CatalogCache
{
    private const VERSION_KEY = 'catalog:version';

    private const TTL = 3600;

    public static function remember(string $key, Closure $callback): mixed
    {
        $version = self::version();
        $cacheKey = "catalog:v{$version}:{$key}";

        $value = Cache::get($cacheKey);

        if ($value === null || self::isUnreadable($value)) {
            // Miss, or a stored value that cannot be read back (a class missing from cache.serializable_classes comes
            // back as an incomplete object): rebuild it and overwrite, rather than serving something broken.
            $value = $callback();
            Cache::put($cacheKey, $value, self::TTL);
        }

        return $value;
    }

    /** True if the value (or, for a collection or paginator, its first item) is a PHP "incomplete class" shell. */
    private static function isUnreadable(mixed $value): bool
    {
        $incomplete = fn ($v) => $v instanceof \__PHP_Incomplete_Class;

        if ($incomplete($value)) {
            return true;
        }

        if ($value instanceof \Illuminate\Support\Collection) {
            return $incomplete($value->first());
        }

        if ($value instanceof \Illuminate\Pagination\AbstractPaginator) {
            return $incomplete($value->items()[0] ?? null);
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if (self::isUnreadable($item)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** A random token, not a counter: concurrent bumps can't collide and a flushed cache can't revive old keys. */
    public static function bump(): void
    {
        Cache::forever(self::VERSION_KEY, (string) Str::uuid());
    }

    private static function version(): string
    {
        return Cache::rememberForever(self::VERSION_KEY, fn () => (string) Str::uuid());
    }
}
