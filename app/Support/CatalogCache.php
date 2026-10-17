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

        return Cache::remember("catalog:v{$version}:{$key}", self::TTL, $callback);
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
