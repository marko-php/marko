<?php

declare(strict_types=1);

namespace Marko\PageCache\Contracts;

use Marko\PageCache\CachePolicy;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

interface PageCacheInterface
{
    /**
     * Look up a cached response for the given request.
     *
     * Returns null when no entry is found, or unconditionally for reverse-proxy
     * drivers (Varnish, NGINX FastCGI cache, Cloudflare/Fastly) where the proxy
     * intercepts cache hits before PHP runs — PHP is only invoked on misses.
     */
    public function lookup(Request $request): ?Response;

    /**
     * Store a response in the cache for the given request under the given policy.
     *
     * The returned Response MAY differ from the input. Proxy drivers (Varnish,
     * NGINX, Cloudflare/Fastly) add headers such as `Cache-Control`,
     * `Surrogate-Control`, and `Surrogate-Key`/`Cache-Tag` here so the proxy
     * knows how to cache and tag the entry. Internal-storage drivers (file,
     * Redis, db) MUST return the input Response unchanged in v1.
     *
     * The middleware uses the returned Response (not the input) when sending
     * downstream, so header decoration added by proxy drivers is honored.
     */
    public function store(
        Request $request,
        Response $response,
        CachePolicy $policy,
    ): Response;

    /**
     * Purge the cached entry for the given URL.
     *
     * v1 limitation: only the canonical GET key for the URL is purged. HEAD
     * entries and any future Vary-axis variants of the same URL are NOT purged
     * in v1.
     *
     * Returns true on success, false on failure.
     */
    public function purgeUrl(string $url): bool;

    /**
     * Purge all cached entries associated with the given cache tag.
     *
     * Drivers MAY purge eagerly (e.g. file driver: walk the tag index now) or
     * lazily (e.g. Varnish: send a BAN request and let the proxy do the work).
     *
     * Returns true indicating the purge was dispatched successfully, false otherwise.
     */
    public function purgeTag(string $tag): bool;

    /**
     * Clear all cached entries managed by this driver.
     *
     * Drivers SHOULD remove all state. Returns true on success, false on failure.
     */
    public function clear(): bool;
}
