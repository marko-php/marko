<?php

declare(strict_types=1);

require_once __DIR__ . '/../../helpers.php';

use Marko\PageCache\CacheKey;
use Marko\PageCache\CachePolicy;
use Marko\Routing\Http\Response;

$tmpDir = null;

beforeEach(function () use (&$tmpDir): void {
    $tmpDir = sys_get_temp_dir() . '/page-cache-test-' . bin2hex(random_bytes(8));
    $this->tmpDir = $tmpDir;
    $this->driver = createPageCacheFileDriver($tmpDir);
});

afterEach(function () use (&$tmpDir): void {
    if ($tmpDir !== null && is_dir($tmpDir)) {
        cleanupPageCacheDir($tmpDir);
    }
    $tmpDir = null;
});

it('returns null on lookup when no entry exists for the request', function (): void {
    $request = createTestRequest('GET', '/test');

    $result = $this->driver->lookup($request);

    expect($result)->toBeNull();
});

it('returns the stored Response on lookup when the entry is fresh', function (): void {
    $request = createTestRequest('GET', '/test');
    $response = new Response(body: 'Hello World', statusCode: 200, headers: ['X-Custom' => 'value']);
    $policy = new CachePolicy(ttl: 3600, tags: []);

    $this->driver->store($request, $response, $policy);
    $result = $this->driver->lookup($request);

    expect($result)->toBeInstanceOf(Response::class)
        ->and($result->statusCode())->toBe(200)
        ->and($result->body())->toBe('Hello World')
        ->and($result->headers())->toBe(['X-Custom' => 'value']);
});

it('returns null on lookup when the entry has expired and deletes the expired file', function (): void {
    $request = createTestRequest('GET', '/test');
    $key = CacheKey::fromRequest($request);
    writeExpiredPageCacheEntry($this->tmpDir, $key->hash());

    $result = $this->driver->lookup($request);

    $cacheFile = $this->tmpDir . '/pages/' . $key->hash() . '.cache';
    expect($result)->toBeNull()
        ->and(file_exists($cacheFile))->toBeFalse();
});

it('stores a Response with status code, body, headers, ttl, and tags', function (): void {
    $request = createTestRequest('GET', '/product/1');
    $response = new Response(body: '<html>product</html>', statusCode: 200, headers: ['Content-Type' => 'text/html']);
    $policy = new CachePolicy(ttl: 600, tags: ['product-1', 'category-5']);

    $this->driver->store($request, $response, $policy);

    $key = CacheKey::fromRequest($request);
    $filePath = $this->tmpDir . '/pages/' . $key->hash() . '.cache';
    $data = unserialize(file_get_contents($filePath));

    expect(file_exists($filePath))->toBeTrue()
        ->and($data['status_code'])->toBe(200)
        ->and($data['body'])->toBe('<html>product</html>')
        ->and($data['headers'])->toBe(['Content-Type' => 'text/html'])
        ->and($data['tags'])->toBe(['product-1', 'category-5'])
        ->and($data['expires_at'])->toBeGreaterThan(time())
        ->and($data['created_at'])->toBeGreaterThan(0);
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
    $driver = createPageCacheFileDriver($this->tmpDir, $defaultTtl);
    $request = createTestRequest('GET', '/test');
    $response = new Response(body: 'body', statusCode: 200);
    $policy = new CachePolicy(ttl: 0, tags: []);

    $driver->store($request, $response, $policy);

    $key = CacheKey::fromRequest($request);
    $filePath = $this->tmpDir . '/pages/' . $key->hash() . '.cache';
    $data = unserialize(file_get_contents($filePath));

    expect($data['expires_at'])->toBeGreaterThan(time() + $defaultTtl - 5)
        ->and($data['expires_at'])->toBeLessThanOrEqual(time() + $defaultTtl + 5);
});

it('uses an explicit ttl from CachePolicy when greater than zero', function (): void {
    $explicitTtl = 120;
    $request = createTestRequest('GET', '/test');
    $response = new Response(body: 'body', statusCode: 200);
    $policy = new CachePolicy(ttl: $explicitTtl, tags: []);

    $this->driver->store($request, $response, $policy);

    $key = CacheKey::fromRequest($request);
    $filePath = $this->tmpDir . '/pages/' . $key->hash() . '.cache';
    $data = unserialize(file_get_contents($filePath));

    expect($data['expires_at'])->toBeGreaterThan(time() + $explicitTtl - 5)
        ->and($data['expires_at'])->toBeLessThanOrEqual(time() + $explicitTtl + 5);
});

it('deletes the corresponding cache file when purgeUrl is called for an existing URL', function (): void {
    $request = createTestRequest('GET', '/products');
    $response = new Response(body: 'products page', statusCode: 200);
    $policy = new CachePolicy(ttl: 3600, tags: []);

    $this->driver->store($request, $response, $policy);

    $key = CacheKey::fromRequest($request);
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

it(
    'parses URL paths and query strings consistently between purgeUrl and store (round-trip a stored URL through purgeUrl)',
    function (): void {
        $request = createTestRequest('GET', '/search', ['q' => 'hello', 'page' => '2']);
        $response = new Response(body: 'search results', statusCode: 200);
        $policy = new CachePolicy(ttl: 3600, tags: []);

        $this->driver->store($request, $response, $policy);

        $key = CacheKey::fromRequest($request);
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
        $policy = new CachePolicy(ttl: 3600, tags: []);

        $this->driver->store($request, $response, $policy);

        $key = CacheKey::fromRequest($request);
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
