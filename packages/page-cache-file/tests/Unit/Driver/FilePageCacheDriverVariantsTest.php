<?php

declare(strict_types=1);

require_once __DIR__ . '/../../helpers.php';

use Marko\PageCache\CachePolicy;
use Marko\Routing\Http\Response;

$tmpDir = null;

beforeEach(function () use (&$tmpDir): void {
    $tmpDir = sys_get_temp_dir() . '/page-cache-variants-test-' . bin2hex(random_bytes(8));
    $this->tmpDir = $tmpDir;
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

it('stores one entry for a path however many unlisted query parameters requests carry', function (): void {
    $driver = createPageCacheFileDriver($this->tmpDir);
    $policy = new CachePolicy(ttl: 3600, tags: []);

    for ($i = 0; $i < 50; $i++) {
        $driver->store(createTestRequest('GET', '/blog', ['x' => (string) $i]), new Response(body: 'blog'), $policy);
    }

    expect(glob($this->tmpDir . '/pages/*.cache'))->toHaveCount(1)
        ->and($driver->lookup(createTestRequest('GET', '/blog', ['utm_source' => 'mail']), [])?->body())
        ->toBe('blog');
});

it('stores a separate entry per value of an allowlisted query parameter', function (): void {
    $driver = createPageCacheFileDriver($this->tmpDir);
    $policy = new CachePolicy(ttl: 3600, tags: [], queryParams: ['page']);

    $driver->store(createTestRequest('GET', '/blog', ['page' => '1', 'x' => 'a']), new Response(body: 'one'), $policy);
    $driver->store(createTestRequest('GET', '/blog', ['page' => '2', 'x' => 'b']), new Response(body: 'two'), $policy);

    expect(glob($this->tmpDir . '/pages/*.cache'))->toHaveCount(2)
        ->and($driver->lookup(createTestRequest('GET', '/blog', ['page' => '1']), ['page'])?->body())->toBe('one')
        ->and($driver->lookup(createTestRequest('GET', '/blog', ['page' => '2', 'y' => 'z']), ['page'])?->body())
        ->toBe('two');
});

it('skips storing new variants of a path once max_variants_per_path is reached', function (): void {
    $driver = createPageCacheFileDriver($this->tmpDir, maxVariantsPerPath: 3);
    $policy = new CachePolicy(ttl: 3600, tags: ['blog'], queryParams: ['page']);

    for ($i = 1; $i <= 10; $i++) {
        $response = new Response(body: "page $i");
        $returned = $driver->store(createTestRequest('GET', '/blog', ['page' => (string) $i]), $response, $policy);

        expect($returned)->toBe($response);
    }

    expect(glob($this->tmpDir . '/pages/*.cache'))->toHaveCount(3)
        ->and(glob($this->tmpDir . '/tags/' . hash('xxh128', 'blog') . '/*'))->toHaveCount(3)
        ->and($driver->lookup(createTestRequest('GET', '/blog', ['page' => '3']), ['page'])?->body())->toBe('page 3')
        ->and($driver->lookup(createTestRequest('GET', '/blog', ['page' => '4']), ['page']))->toBeNull();
});

it('still refreshes an existing variant of a path at the limit', function (): void {
    $driver = createPageCacheFileDriver($this->tmpDir, maxVariantsPerPath: 1);
    $policy = new CachePolicy(ttl: 3600, tags: []);
    $request = createTestRequest('GET', '/blog');

    $driver->store($request, new Response(body: 'old'), $policy);
    $driver->store($request, new Response(body: 'new'), $policy);

    expect($driver->lookup($request, [])?->body())->toBe('new');
});

it('counts the limit per path so other paths keep caching', function (): void {
    $driver = createPageCacheFileDriver($this->tmpDir, maxVariantsPerPath: 1);
    $policy = new CachePolicy(ttl: 3600, tags: [], queryParams: ['page']);

    $driver->store(createTestRequest('GET', '/blog', ['page' => '1']), new Response(body: 'blog'), $policy);
    $driver->store(createTestRequest('GET', '/news', ['page' => '1']), new Response(body: 'news'), $policy);

    expect($driver->lookup(createTestRequest('GET', '/news', ['page' => '1']), ['page'])?->body())->toBe('news');
});

it('counts variants across hosts so Host header values cannot flood a path', function (): void {
    $driver = createPageCacheFileDriver($this->tmpDir, maxVariantsPerPath: 2);
    $policy = new CachePolicy(ttl: 3600, tags: []);

    foreach (['a.test', 'b.test', 'c.test', 'd.test'] as $host) {
        $driver->store(createTestRequest('GET', '/', host: $host), new Response(body: $host), $policy);
    }

    expect(glob($this->tmpDir . '/pages/*.cache'))->toHaveCount(2);
});

it('frees a slot when a variant of the path is purged', function (): void {
    $driver = createPageCacheFileDriver($this->tmpDir, maxVariantsPerPath: 2);
    $policy = new CachePolicy(ttl: 3600, tags: [], queryParams: ['page']);

    $driver->store(createTestRequest('GET', '/blog', ['page' => '1']), new Response(body: 'one'), $policy);
    $driver->store(createTestRequest('GET', '/blog', ['page' => '2']), new Response(body: 'two'), $policy);
    $driver->purgeUrl('http://example.com/blog?page=1');
    $driver->store(createTestRequest('GET', '/blog', ['page' => '3']), new Response(body: 'three'), $policy);

    expect($driver->lookup(createTestRequest('GET', '/blog', ['page' => '3']), ['page'])?->body())->toBe('three');
});

it('does not limit variants when max_variants_per_path is zero', function (): void {
    $driver = createPageCacheFileDriver($this->tmpDir, maxVariantsPerPath: 0);
    $policy = new CachePolicy(ttl: 3600, tags: [], queryParams: ['page']);

    for ($i = 1; $i <= 5; $i++) {
        $driver->store(createTestRequest('GET', '/blog', ['page' => (string) $i]), new Response(body: 'x'), $policy);
    }

    expect(glob($this->tmpDir . '/pages/*.cache'))->toHaveCount(5)
        ->and(is_dir($this->tmpDir . '/variants'))->toBeFalse();
});
