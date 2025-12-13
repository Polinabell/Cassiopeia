<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Simple Redis cache service wrapper.
 */
class CacheService
{
    /**
     * Get or compute a cached value.
     */
    public function remember(string $key, int $ttlSeconds, callable $callback): mixed
    {
        return Cache::remember($key, $ttlSeconds, $callback);
    }

    /**
     * Store a value in cache.
     */
    public function put(string $key, mixed $value, int $ttlSeconds): void
    {
        Cache::put($key, $value, $ttlSeconds);
    }

    /**
     * Get a value from cache.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return Cache::get($key, $default);
    }

    /**
     * Remove a value from cache.
     */
    public function forget(string $key): void
    {
        Cache::forget($key);
    }

    /**
     * Check if cache has a key.
     */
    public function has(string $key): bool
    {
        return Cache::has($key);
    }

    /**
     * Clear all cache.
     */
    public function flush(): void
    {
        Cache::flush();
    }
}

