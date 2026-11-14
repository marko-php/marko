<?php

declare(strict_types=1);

require_once __DIR__ . '/../../helpers.php';

use Marko\PageCache\CacheKey;
use Marko\PageCache\CachePolicy;
use Marko\Routing\Http\Response;

$tmpDir = null;

beforeEach(function () use (&$tmpDir): void {
    $tmpDir = sys_get_temp_dir() . '/page-cache-tags-test-' . bin2hex(random_bytes(8));
    $this->tmpDir = $tmpDir;
    $this->driver = createPageCacheFileDriver($tmpDir);
    $this->tagDir = fn (string $tag): string => $tmpDir . '/tags/' . hash('xxh128', $tag);
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

it('writes an empty marker file per page into each tag directory when storing with tags', function (): void {
    $request = createTestRequest('GET', '/product/1');
    $response = new Response(body: '<html>product</html>', statusCode: 200);
    $policy = new CachePolicy(ttl: 3600, tags: ['product-1', 'category-5']);

    $this->driver->store($request, $response, $policy);

    $hash = CacheKey::fromRequest($request, [])->hash();
    $marker1 = ($this->tagDir)('product-1') . '/' . $hash;
    $marker2 = ($this->tagDir)('category-5') . '/' . $hash;

    expect(is_file($marker1))->toBeTrue()
        ->and(is_file($marker2))->toBeTrue()
        ->and(filesize($marker1))->toBe(0);
});

it('keeps a single marker when the same page is stored twice under a tag', function (): void {
    $request = createTestRequest('GET', '/product/1');
    $response = new Response(body: '<html>product</html>', statusCode: 200);
    $policy = new CachePolicy(ttl: 3600, tags: ['product-1']);

    $this->driver->store($request, $response, $policy);
    $this->driver->store($request, $response, $policy);

    expect(glob(($this->tagDir)('product-1') . '/*'))->toHaveCount(1);
});

it('does not read or rewrite a shared index when tagging pages', function (): void {
    $response = new Response(body: 'body', statusCode: 200);
    $policy = new CachePolicy(ttl: 3600, tags: ['product']);

    for ($i = 0; $i < 25; $i++) {
        $this->driver->store(createTestRequest('GET', "/product/$i"), $response, $policy);
    }

    $markers = glob(($this->tagDir)('product') . '/*') ?: [];

    expect($markers)->toHaveCount(25)
        ->and(array_sum(array_map(filesize(...), $markers)))->toBe(0)
        ->and(glob($this->tmpDir . '/tags/*.tag'))->toBeEmpty();
});

it('deletes all pages tagged with a given tag when purgeTag is called', function (): void {
    $request1 = createTestRequest('GET', '/product/1');
    $request2 = createTestRequest('GET', '/product/2');
    $untagged = createTestRequest('GET', '/about');
    $response = new Response(body: 'body', statusCode: 200);
    $policy = new CachePolicy(ttl: 3600, tags: ['product']);

    $this->driver->store($request1, $response, $policy);
    $this->driver->store($request2, $response, $policy);
    $this->driver->store($untagged, $response, new CachePolicy(ttl: 3600, tags: ['about']));

    $pageFile1 = $this->tmpDir . '/pages/' . CacheKey::fromRequest($request1, [])->hash() . '.cache';
    $pageFile2 = $this->tmpDir . '/pages/' . CacheKey::fromRequest($request2, [])->hash() . '.cache';
    $untaggedFile = $this->tmpDir . '/pages/' . CacheKey::fromRequest($untagged, [])->hash() . '.cache';

    expect(file_exists($pageFile1))->toBeTrue()
        ->and(file_exists($pageFile2))->toBeTrue();

    $this->driver->purgeTag('product');

    expect(file_exists($pageFile1))->toBeFalse()
        ->and(file_exists($pageFile2))->toBeFalse()
        ->and(file_exists($untaggedFile))->toBeTrue();
});

it('deletes the tag directory after purgeTag completes', function (): void {
    $request = createTestRequest('GET', '/product/1');
    $response = new Response(body: 'body', statusCode: 200);
    $policy = new CachePolicy(ttl: 3600, tags: ['product-tag']);

    $this->driver->store($request, $response, $policy);

    expect(is_dir(($this->tagDir)('product-tag')))->toBeTrue();

    $this->driver->purgeTag('product-tag');

    expect(file_exists(($this->tagDir)('product-tag')))->toBeFalse();
});

it('returns true from purgeTag when no tag directory exists for that tag', function (): void {
    $result = $this->driver->purgeTag('nonexistent-tag');

    expect($result)->toBeTrue();
});

it('tolerates missing page files referenced by a tag marker', function (): void {
    $request = createTestRequest('GET', '/product/1');
    $response = new Response(body: 'body', statusCode: 200);
    $policy = new CachePolicy(ttl: 3600, tags: ['product']);

    $this->driver->store($request, $response, $policy);

    $pageFile = $this->tmpDir . '/pages/' . CacheKey::fromRequest($request, [])->hash() . '.cache';
    @unlink($pageFile);

    expect(file_exists($pageFile))->toBeFalse();

    $result = $this->driver->purgeTag('product');

    expect($result)->toBeTrue()
        ->and(file_exists(($this->tagDir)('product')))->toBeFalse();
});

it('ignores marker files whose names are not page hashes when purging a tag', function (): void {
    $outside = $this->tmpDir . '/pages/keep.cache';
    mkdir($this->tmpDir . '/pages', 0755, true);
    file_put_contents($outside, 'keep');
    mkdir(($this->tagDir)('product'), 0755, true);
    touch(($this->tagDir)('product') . '/keep');

    $result = $this->driver->purgeTag('product');

    expect($result)->toBeTrue()
        ->and(file_exists($outside))->toBeTrue();
});

it('deletes all tag directories in addition to page files when clear is called', function (): void {
    $request1 = createTestRequest('GET', '/page1');
    $request2 = createTestRequest('GET', '/page2');
    $response = new Response(body: 'body', statusCode: 200);
    $policy1 = new CachePolicy(ttl: 3600, tags: ['tag-a']);
    $policy2 = new CachePolicy(ttl: 3600, tags: ['tag-b']);

    $this->driver->store($request1, $response, $policy1);
    $this->driver->store($request2, $response, $policy2);

    $tagsDir = $this->tmpDir . '/tags';
    expect(glob($tagsDir . '/*'))->toHaveCount(2);

    $this->driver->clear();

    expect(glob($tagsDir . '/*') ?: [])->toBeEmpty()
        ->and(glob($this->tmpDir . '/pages/*.cache') ?: [])->toBeEmpty()
        ->and(glob($this->tmpDir . '/variants/*') ?: [])->toBeEmpty();
});

it('purges pages listed in a legacy serialized tag index written by earlier versions', function (): void {
    $request = createTestRequest('GET', '/legacy');
    $this->driver->store($request, new Response(body: 'old', statusCode: 200), new CachePolicy(ttl: 3600, tags: []));
    $hash = CacheKey::fromRequest($request, [])->hash();

    $legacyIndex = $this->tmpDir . '/tags/' . hash('xxh128', 'legacy') . '.tag';
    mkdir($this->tmpDir . '/tags', 0755, true);
    file_put_contents($legacyIndex, serialize([$hash]));

    $result = $this->driver->purgeTag('legacy');

    expect($result)->toBeTrue()
        ->and($this->driver->lookup($request, []))->toBeNull()
        ->and(file_exists($legacyIndex))->toBeFalse();
});

it('removes legacy tag index files when clear is called', function (): void {
    $legacyIndex = $this->tmpDir . '/tags/' . hash('xxh128', 'legacy') . '.tag';
    mkdir($this->tmpDir . '/tags', 0755, true);
    file_put_contents($legacyIndex, serialize(['0123456789abcdef0123456789abcdef']));

    $this->driver->clear();

    expect(file_exists($legacyIndex))->toBeFalse();
});

it('does not instantiate a disallowed class when decoding a tampered legacy tag index', function (): void {
    $tag = 'tampered-tag';
    $tagsDir = $this->tmpDir . '/tags';

    if (!is_dir($tagsDir)) {
        mkdir($tagsDir, 0755, true);
    }

    $tagFile = $tagsDir . '/' . hash('xxh128', $tag) . '.tag';

    // Write a tag index file that embeds a serialized stdClass in the hashes array.
    // With allowed_classes => false this object becomes __PHP_Incomplete_Class and
    // is not instantiated. The purgeTag call must not throw and must succeed.
    $tamperedIndex = serialize([new stdClass(), 'legitimate-hash-value']);
    file_put_contents($tagFile, $tamperedIndex);

    $result = $this->driver->purgeTag($tag);

    expect($result)->toBeTrue();
});
