<?php

declare(strict_types=1);

use Marko\PageCache\CacheKey;
use Marko\PageCache\CachePolicy;
use Marko\Routing\Http\Response;

$tmpDir = null;

beforeEach(function () use (&$tmpDir): void {
    $tmpDir = sys_get_temp_dir() . '/page-cache-tags-test-' . bin2hex(random_bytes(8));
    $this->tmpDir = $tmpDir;
    $this->driver = createDriver($tmpDir);
});

afterEach(function () use (&$tmpDir): void {
    if ($tmpDir !== null && is_dir($tmpDir)) {
        cleanupDir($tmpDir);
    }
    $tmpDir = null;
});

it('writes a tag index file when storing a response with tags', function (): void {
    $request = createTestRequest('GET', '/product/1');
    $response = new Response(body: '<html>product</html>', statusCode: 200);
    $policy = new CachePolicy(ttl: 3600, tags: ['product-1', 'category-5']);

    $this->driver->store($request, $response, $policy);

    $tagFile1 = $this->tmpDir . '/tags/' . hash('xxh128', 'product-1') . '.tag';
    $tagFile2 = $this->tmpDir . '/tags/' . hash('xxh128', 'category-5') . '.tag';

    expect(file_exists($tagFile1))->toBeTrue()
        ->and(file_exists($tagFile2))->toBeTrue();
});

it('appends a page hash to an existing tag index without duplicating it', function (): void {
    $request = createTestRequest('GET', '/product/1');
    $response = new Response(body: '<html>product</html>', statusCode: 200);
    $policy = new CachePolicy(ttl: 3600, tags: ['product-1']);

    $this->driver->store($request, $response, $policy);
    $this->driver->store($request, $response, $policy);

    $tagFile = $this->tmpDir . '/tags/' . hash('xxh128', 'product-1') . '.tag';
    $hashes = unserialize(file_get_contents($tagFile));

    expect($hashes)->toHaveCount(1);
});

it('deletes all pages tagged with a given tag when purgeTag is called', function (): void {
    $request1 = createTestRequest('GET', '/product/1');
    $request2 = createTestRequest('GET', '/product/2');
    $response = new Response(body: 'body', statusCode: 200);
    $policy = new CachePolicy(ttl: 3600, tags: ['product']);

    $this->driver->store($request1, $response, $policy);
    $this->driver->store($request2, $response, $policy);

    $key1 = CacheKey::fromRequest($request1);
    $key2 = CacheKey::fromRequest($request2);
    $pageFile1 = $this->tmpDir . '/pages/' . $key1->hash() . '.cache';
    $pageFile2 = $this->tmpDir . '/pages/' . $key2->hash() . '.cache';

    expect(file_exists($pageFile1))->toBeTrue()
        ->and(file_exists($pageFile2))->toBeTrue();

    $this->driver->purgeTag('product');

    expect(file_exists($pageFile1))->toBeFalse()
        ->and(file_exists($pageFile2))->toBeFalse();
});

it('deletes the tag index file after purgeTag completes', function (): void {
    $request = createTestRequest('GET', '/product/1');
    $response = new Response(body: 'body', statusCode: 200);
    $policy = new CachePolicy(ttl: 3600, tags: ['product-tag']);

    $this->driver->store($request, $response, $policy);

    $tagFile = $this->tmpDir . '/tags/' . hash('xxh128', 'product-tag') . '.tag';
    expect(file_exists($tagFile))->toBeTrue();

    $this->driver->purgeTag('product-tag');

    expect(file_exists($tagFile))->toBeFalse();
});

it('returns true from purgeTag when no tag index file exists for that tag', function (): void {
    $result = $this->driver->purgeTag('nonexistent-tag');

    expect($result)->toBeTrue();
});

it('tolerates missing page files referenced by a tag index', function (): void {
    $request = createTestRequest('GET', '/product/1');
    $response = new Response(body: 'body', statusCode: 200);
    $policy = new CachePolicy(ttl: 3600, tags: ['product']);

    $this->driver->store($request, $response, $policy);

    $key = CacheKey::fromRequest($request);
    $pageFile = $this->tmpDir . '/pages/' . $key->hash() . '.cache';
    @unlink($pageFile);

    expect(file_exists($pageFile))->toBeFalse();

    $result = $this->driver->purgeTag('product');

    expect($result)->toBeTrue();
});

it('deletes all tag index files in addition to page files when clear is called', function (): void {
    $request1 = createTestRequest('GET', '/page1');
    $request2 = createTestRequest('GET', '/page2');
    $response = new Response(body: 'body', statusCode: 200);
    $policy1 = new CachePolicy(ttl: 3600, tags: ['tag-a']);
    $policy2 = new CachePolicy(ttl: 3600, tags: ['tag-b']);

    $this->driver->store($request1, $response, $policy1);
    $this->driver->store($request2, $response, $policy2);

    $tagsDir = $this->tmpDir . '/tags';
    $tagFiles = glob($tagsDir . '/*.tag') ?: [];
    expect($tagFiles)->toHaveCount(2);

    $this->driver->clear();

    $remainingTagFiles = glob($tagsDir . '/*.tag') ?: [];
    $pagesDir = $this->tmpDir . '/pages';
    $remainingPageFiles = glob($pagesDir . '/*.cache') ?: [];

    expect($remainingTagFiles)->toBeEmpty()
        ->and($remainingPageFiles)->toBeEmpty();
});
