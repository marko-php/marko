<?php

declare(strict_types=1);

require_once __DIR__ . '/../../helpers.php';

use Marko\PageCache\CacheKey;
use Marko\PageCache\CachePolicy;
use Marko\PageCache\Exceptions\PageCacheException;
use Marko\Routing\Http\Response;
use Marko\Testing\Fake\FakeClock;

$tmpDir = null;

beforeEach(function () use (&$tmpDir): void {
    $tmpDir = sys_get_temp_dir() . '/page-cache-test-' . bin2hex(random_bytes(8));
    $this->tmpDir = $tmpDir;
    $this->clock = new FakeClock('2026-01-01 12:00:00 UTC');
    $this->driver = createPageCacheFileDriver($tmpDir, clock: $this->clock);
});

afterEach(function () use (&$tmpDir): void {
    if (
        $tmpDir !== null
        && is_dir($tmpDir)
    ) {
        cleanupPageCacheDir($tmpDir);
    }
    $tmpDir = null;
});

it('returns null on lookup when no entry exists for the request', function (): void {
    $request = createTestRequest('GET', '/test');

    $result = $this->driver->lookup($request, []);

    expect($result)->toBeNull();
});

it('returns the stored Response on lookup when the entry is fresh', function (): void {
    $request = createTestRequest('GET', '/test');
    $response = new Response(body: 'Hello World', statusCode: 200, headers: ['X-Custom' => 'value']);
    $policy = new CachePolicy(ttl: 3600, tags: []);

    $this->driver->store($request, $response, $policy);
    $result = $this->driver->lookup($request, []);

    expect($result)->toBeInstanceOf(Response::class)
        ->and($result->statusCode())->toBe(200)
        ->and($result->body())->toBe('Hello World')
        ->and($result->headers())->toBe(['X-Custom' => 'value']);
});

it('returns null on lookup when the entry has expired and deletes the expired file', function (): void {
    $request = createTestRequest('GET', '/test');
    $key = CacheKey::fromRequest($request, []);
    writeExpiredPageCacheEntry($this->tmpDir, $key->hash(), $this->clock->now()->getTimestamp());

    $result = $this->driver->lookup($request, []);

    $cacheFile = $this->tmpDir . '/pages/' . $key->hash() . '.cache';
    expect($result)->toBeNull()
        ->and(file_exists($cacheFile))->toBeFalse();
});

it('stores a Response with status code, body, headers, ttl, and tags', function (): void {
    $request = createTestRequest('GET', '/product/1');
    $response = new Response(body: '<html>product</html>', statusCode: 200, headers: ['Content-Type' => 'text/html']);
    $policy = new CachePolicy(ttl: 600, tags: ['product-1', 'category-5']);

    $this->driver->store($request, $response, $policy);

    $key = CacheKey::fromRequest($request, []);
    $filePath = $this->tmpDir . '/pages/' . $key->hash() . '.cache';
    $data = unserialize(file_get_contents($filePath));

    expect(file_exists($filePath))->toBeTrue()
        ->and($data['status_code'])->toBe(200)
        ->and($data['body'])->toBe('<html>product</html>')
        ->and($data['headers'])->toBe(['Content-Type' => 'text/html'])
        ->and($data['tags'])->toBe(['product-1', 'category-5'])
        ->and($data['expires_at'])->toBe($this->clock->now()->getTimestamp() + 600)
        ->and($data['created_at'])->toBe($this->clock->now()->getTimestamp());
});

it('returns the same Response from store unchanged in v1', function (): void {
    $request = createTestRequest('GET', '/test');
    $response = new Response(body: 'body', statusCode: 200, headers: ['X-Header' => 'val']);
    $policy = new CachePolicy(ttl: 3600, tags: []);

    $returned = $this->driver->store($request, $response, $policy);

    expect($returned)->toBe($response);
});

it('uses the configured default ttl when CachePolicy ttl equals zero', function (): void {
    $defaultTtl = 1800;
    $driver = createPageCacheFileDriver($this->tmpDir, $defaultTtl, $this->clock);
    $request = createTestRequest('GET', '/test');
    $response = new Response(body: 'body', statusCode: 200);
    $policy = new CachePolicy(ttl: 0, tags: []);

    $driver->store($request, $response, $policy);

    $key = CacheKey::fromRequest($request, []);
    $filePath = $this->tmpDir . '/pages/' . $key->hash() . '.cache';
    $data = unserialize(file_get_contents($filePath));

    expect($data['expires_at'])->toBe($this->clock->now()->getTimestamp() + $defaultTtl);
});

it('uses an explicit ttl from CachePolicy when greater than zero', function (): void {
    $explicitTtl = 120;
    $request = createTestRequest('GET', '/test');
    $response = new Response(body: 'body', statusCode: 200);
    $policy = new CachePolicy(ttl: $explicitTtl, tags: []);

    $this->driver->store($request, $response, $policy);

    $key = CacheKey::fromRequest($request, []);
    $filePath = $this->tmpDir . '/pages/' . $key->hash() . '.cache';
    $data = unserialize(file_get_contents($filePath));

    expect($data['expires_at'])->toBe($this->clock->now()->getTimestamp() + $explicitTtl);
});

it('deletes the corresponding cache file when purgeUrl is called for an existing URL', function (): void {
    $request = createTestRequest('GET', '/products');
    $response = new Response(body: 'products page', statusCode: 200);
    $policy = new CachePolicy(ttl: 3600, tags: []);

    $this->driver->store($request, $response, $policy);

    $key = CacheKey::fromRequest($request, []);
    $filePath = $this->tmpDir . '/pages/' . $key->hash() . '.cache';

    expect(file_exists($filePath))->toBeTrue();

    $result = $this->driver->purgeUrl('http://example.com/products');

    expect($result)->toBeTrue()
        ->and(file_exists($filePath))->toBeFalse();
});

it('returns true from purgeUrl when no entry exists', function (): void {
    $result = $this->driver->purgeUrl('http://example.com/nonexistent');

    expect($result)->toBeTrue();
});

it('keeps pages for the same path on different hosts apart', function (): void {
    $policy = new CachePolicy(ttl: 3600, tags: []);
    $this->driver->store(createTestRequest('GET', '/home', host: 'a.example.com'), new Response(body: 'A'), $policy);

    expect($this->driver->lookup(createTestRequest('GET', '/home', host: 'b.example.com'), []))->toBeNull()
        ->and($this->driver->lookup(createTestRequest('GET', '/home', host: 'a.example.com'), [])?->body())->toBe('A');
});

it('keeps pages for the same path over http and https apart', function (): void {
    $policy = new CachePolicy(ttl: 3600, tags: []);
    $this->driver->store(createTestRequest('GET', '/home', https: true), new Response(body: 'secure'), $policy);

    expect($this->driver->lookup(createTestRequest('GET', '/home'), []))->toBeNull()
        ->and($this->driver->lookup(createTestRequest('GET', '/home', https: true), [])?->body())->toBe('secure');
});

it('purges a URL over both http and https', function (): void {
    $policy = new CachePolicy(ttl: 3600, tags: []);
    $this->driver->store(createTestRequest('GET', '/home'), new Response(body: 'plain'), $policy);
    $this->driver->store(createTestRequest('GET', '/home', https: true), new Response(body: 'secure'), $policy);

    $this->driver->purgeUrl('https://example.com/home');

    expect($this->driver->lookup(createTestRequest('GET', '/home'), []))->toBeNull()
        ->and($this->driver->lookup(createTestRequest('GET', '/home', https: true), []))->toBeNull();
});

it('purges a relative URL for every exact trusted host', function (): void {
    $driver = createPageCacheFileDriver($this->tmpDir, trustedHosts: ['example.com', 'www.example.com', '*.cdn.test']);
    $policy = new CachePolicy(ttl: 3600, tags: []);
    $driver->store(createTestRequest('GET', '/home'), new Response(body: 'apex'), $policy);
    $driver->store(createTestRequest('GET', '/home', host: 'www.example.com'), new Response(body: 'www'), $policy);

    expect($driver->purgeUrl('/home'))->toBeTrue()
        ->and($driver->lookup(createTestRequest('GET', '/home'), []))->toBeNull()
        ->and($driver->lookup(createTestRequest('GET', '/home', host: 'www.example.com'), []))->toBeNull();
});

it('fails loudly when purging a relative URL without any trusted host', function (): void {
    expect(fn () => $this->driver->purgeUrl('/home'))
        ->toThrow(PageCacheException::class, "Cannot purge '/home'");
});

it(
    'parses URL paths and query strings consistently between purgeUrl and store (round-trip a stored URL through purgeUrl)',
    function (): void {
        $request = createTestRequest('GET', '/search', ['q' => 'hello', 'page' => '2']);
        $response = new Response(body: 'search results', statusCode: 200);
        $policy = new CachePolicy(ttl: 3600, tags: [], queryParams: ['q', 'page']);

        $this->driver->store($request, $response, $policy);

        $key = CacheKey::fromRequest($request, ['q', 'page']);
        $filePath = $this->tmpDir . '/pages/' . $key->hash() . '.cache';

        expect(file_exists($filePath))->toBeTrue();

        $result = $this->driver->purgeUrl('http://example.com/search?q=hello&page=2');

        expect($result)->toBeTrue()
            ->and(file_exists($filePath))->toBeFalse();
    },
);

it(
    'normalizes query string ordering when purging by URL (purgeUrl with "?b=2&a=1" purges an entry stored with "?a=1&b=2")',
    function (): void {
        $request = createTestRequest('GET', '/items', ['a' => '1', 'b' => '2']);
        $response = new Response(body: 'items page', statusCode: 200);
        $policy = new CachePolicy(ttl: 3600, tags: [], queryParams: ['a', 'b']);

        $this->driver->store($request, $response, $policy);

        $key = CacheKey::fromRequest($request, ['a', 'b']);
        $filePath = $this->tmpDir . '/pages/' . $key->hash() . '.cache';

        expect(file_exists($filePath))->toBeTrue();

        $result = $this->driver->purgeUrl('http://example.com/items?b=2&a=1');

        expect($result)->toBeTrue()
            ->and(file_exists($filePath))->toBeFalse();
    },
);

it('deletes all page cache files when clear is called', function (): void {
    $request1 = createTestRequest('GET', '/page1');
    $request2 = createTestRequest('GET', '/page2');
    $response = new Response(body: 'body', statusCode: 200);
    $policy = new CachePolicy(ttl: 3600, tags: []);

    $this->driver->store($request1, $response, $policy);
    $this->driver->store($request2, $response, $policy);

    $pagesDir = $this->tmpDir . '/pages';
    $cacheFiles = glob($pagesDir . '/*.cache') ?: [];

    expect($cacheFiles)->toHaveCount(2);

    $result = $this->driver->clear();

    $remainingFiles = glob($pagesDir . '/*.cache') ?: [];

    expect($result)->toBeTrue()
        ->and($remainingFiles)->toBeEmpty();
});

it('returns true from clear when the cache directory does not exist', function (): void {
    $result = $this->driver->clear();

    expect($result)->toBeTrue();
});

it('still round-trips a legitimately stored page-cache entry', function (): void {
    $request = createTestRequest('GET', '/roundtrip');
    $response = new Response(body: 'Hello Roundtrip', statusCode: 200, headers: ['X-Test' => 'value']);
    $policy = new CachePolicy(ttl: 3600, tags: []);

    $this->driver->store($request, $response, $policy);
    $result = $this->driver->lookup($request, []);

    expect($result)->toBeInstanceOf(Response::class)
        ->and($result->body())->toBe('Hello Roundtrip')
        ->and($result->statusCode())->toBe(200)
        ->and($result->headers())->toBe(['X-Test' => 'value']);
});

it('hydrates a cached response with no cookies', function (): void {
    $request = createTestRequest('GET', '/no-cookies');
    $response = new Response(body: 'no cookies here', statusCode: 200);
    $policy = new CachePolicy(ttl: 3600, tags: []);

    $this->driver->store($request, $response, $policy);
    $result = $this->driver->lookup($request, []);

    expect($result)->toBeInstanceOf(Response::class)
        ->and($result->cookies())->toBeEmpty();
});

it('hydrates a cache entry written before cookies existed without error', function (): void {
    $request = createTestRequest('GET', '/legacy');
    $key = CacheKey::fromRequest($request, []);
    $pagesDir = $this->tmpDir . '/pages';

    if (!is_dir($pagesDir)) {
        mkdir($pagesDir, 0755, true);
    }

    $legacyPayload = serialize([
        'status_code' => 200,
        'body' => 'legacy body',
        'headers' => ['X-Legacy' => 'yes'],
        'expires_at' => $this->clock->now()->getTimestamp() + 9999,
        'created_at' => $this->clock->now()->getTimestamp(),
    ]);

    file_put_contents($pagesDir . '/' . $key->hash() . '.cache', $legacyPayload);

    $result = $this->driver->lookup($request, []);

    expect($result)->toBeInstanceOf(Response::class)
        ->and($result->body())->toBe('legacy body')
        ->and($result->cookies())->toBeEmpty();
});

it('does not instantiate a disallowed class when decoding a tampered page-cache payload', function (): void {
    $request = createTestRequest('GET', '/tampered');
    $key = CacheKey::fromRequest($request, []);
    $pagesDir = $this->tmpDir . '/pages';

    if (!is_dir($pagesDir)) {
        mkdir($pagesDir, 0755, true);
    }

    // Write a page-cache file whose payload embeds a serialized stdClass object
    // as the body. With allowed_classes => false, the object will NOT be instantiated
    // (it becomes __PHP_Incomplete_Class), so the Response body will not be a stdClass.
    // Without the hardening (bare unserialize), the body WOULD be a live stdClass.
    $payloadWithObject = serialize([
        'status_code' => 200,
        'body' => new stdClass(),
        'headers' => [],
        'expires_at' => $this->clock->now()->getTimestamp() + 9999,
        'created_at' => $this->clock->now()->getTimestamp(),
    ]);

    file_put_contents($pagesDir . '/' . $key->hash() . '.cache', $payloadWithObject);

    $result = $this->driver->lookup($request, []);

    // The driver must not return a Response containing a live stdClass body.
    // Either it returns null (guards reject the non-string body) or it returns a
    // Response whose body is NOT a stdClass instance.
    expect($result)->toBeNull();
});

it('serves a stored page until its ttl has elapsed on the clock', function (): void {
    $request = createTestRequest('GET', '/clock');
    $this->driver->store($request, new Response(body: 'fresh', statusCode: 200), new CachePolicy(ttl: 60, tags: []));

    $this->clock->travel('+60 seconds');

    expect($this->driver->lookup($request, [])?->body())->toBe('fresh');
});

it('misses a stored page once the clock passes its expiry', function (): void {
    $request = createTestRequest('GET', '/clock');
    $this->driver->store($request, new Response(body: 'fresh', statusCode: 200), new CachePolicy(ttl: 60, tags: []));

    $this->clock->travel('+61 seconds');
    $cacheFile = $this->tmpDir . '/pages/' . CacheKey::fromRequest($request, [])->hash() . '.cache';

    expect($this->driver->lookup($request, []))->toBeNull()
        ->and(file_exists($cacheFile))->toBeFalse();
});

it('serves an entry stored with a zero effective ttl', function (): void {
    $driver = createPageCacheFileDriver($this->tmpDir, defaultTtl: 0, clock: $this->clock);
    $request = createTestRequest('GET', '/forever');

    $driver->store($request, new Response(body: 'forever', statusCode: 200), new CachePolicy(ttl: 0, tags: []));

    expect($driver->lookup($request, [])?->body())->toBe('forever');
});

it('keeps serving a never-expiring entry however far the clock advances', function (): void {
    $driver = createPageCacheFileDriver($this->tmpDir, defaultTtl: 0, clock: $this->clock);
    $request = createTestRequest('GET', '/forever');

    $driver->store($request, new Response(body: 'forever', statusCode: 200), new CachePolicy(ttl: 0, tags: []));
    $this->clock->travel('+50 years');

    expect($driver->lookup($request, [])?->body())->toBe('forever');
});

it('removes a never-expiring entry with purgeUrl, purgeTag and clear', function (): void {
    $driver = createPageCacheFileDriver($this->tmpDir, defaultTtl: 0, clock: $this->clock);
    $request = createTestRequest('GET', '/forever');
    $response = new Response(body: 'forever', statusCode: 200);
    $policy = new CachePolicy(ttl: 0, tags: ['pages']);

    $driver->store($request, $response, $policy);
    $servedBeforePurge = $driver->lookup($request, []);
    $driver->purgeUrl('http://example.com/forever');
    $afterPurgeUrl = $driver->lookup($request, []);

    $driver->store($request, $response, $policy);
    $driver->purgeTag('pages');
    $afterPurgeTag = $driver->lookup($request, []);

    $driver->store($request, $response, $policy);
    $driver->clear();
    $afterClear = $driver->lookup($request, []);

    expect($servedBeforePurge)->toBeInstanceOf(Response::class)
        ->and($afterPurgeUrl)->toBeNull()
        ->and($afterPurgeTag)->toBeNull()
        ->and($afterClear)->toBeNull();
});

it('treats a payload without expires_at as a miss', function (): void {
    $request = createTestRequest('GET', '/corrupt');
    writePageCachePayload($this->tmpDir, CacheKey::fromRequest($request, [])->hash(), [
        'status_code' => 200,
        'body' => 'corrupt',
        'headers' => [],
        'created_at' => $this->clock->now()->getTimestamp(),
    ]);

    expect($this->driver->lookup($request, []))->toBeNull();
});

it('treats a payload with a non-integer expires_at as a miss', function (): void {
    $request = createTestRequest('GET', '/corrupt');
    writePageCachePayload($this->tmpDir, CacheKey::fromRequest($request, [])->hash(), [
        'status_code' => 200,
        'body' => 'corrupt',
        'headers' => [],
        'expires_at' => '2099-01-01',
        'created_at' => $this->clock->now()->getTimestamp(),
    ]);

    expect($this->driver->lookup($request, []))->toBeNull();
});
