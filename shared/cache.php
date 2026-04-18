<?php
/**
 * Tiny file-based cache used by read-only endpoints like receiver-status.
 *
 * Intentionally minimal: no locking for reads (occasional torn reads are
 * preferable to a hot lock), atomic writes via tmp+rename, and TTL-based
 * expiration.  Best suited for short-lived caches (< 1 minute) of data that
 * is cheap to recompute if missed.
 *
 * Not safe for data that must be authoritative (use JSON files with locking
 * via shared/zones.php style atomic writes for that).
 *
 * @author Castle Fun Center AV Control System
 */

/**
 * Directory used for cache storage.  Created on first write.
 */
function _simpleCacheDir() {
    return __DIR__ . '/.cache';
}

function _simpleCachePath(string $key): string {
    // Key is hashed to keep paths short and filesystem-safe
    return _simpleCacheDir() . '/' . preg_replace('/[^a-z0-9_-]/i', '_', $key) . '.' . substr(sha1($key), 0, 8) . '.cache';
}

/**
 * Fetch a cached entry if present and not yet expired.
 *
 * @param string $key Cache key
 * @param int    $ttl Lifetime in seconds
 * @return mixed|null  The cached value, or null when absent/expired.
 */
function cacheGet(string $key, int $ttl) {
    $path = _simpleCachePath($key);
    if (!is_file($path)) return null;
    $mtime = @filemtime($path);
    if ($mtime === false) return null;
    if ((time() - $mtime) >= $ttl) return null;
    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') return null;
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

/**
 * Write a value to the cache.  Failures are silently swallowed — caching is
 * a performance optimisation, never a correctness requirement.
 */
function cacheSet(string $key, array $value): void {
    $dir = _simpleCacheDir();
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $path = _simpleCachePath($key);
    $tmp = $path . '.tmp.' . getmypid();
    $json = json_encode($value);
    if ($json === false) return;
    if (@file_put_contents($tmp, $json) === false) return;
    @rename($tmp, $path);
}
