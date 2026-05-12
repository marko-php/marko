<?php

declare(strict_types=1);

use Marko\PageCache\CacheKey;
use Marko\Routing\Http\Request;

it('builds a cache key from method, path, and query string', function (): void {
    $request = new Request(
        server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/products?color=red'],
        query: ['color' => 'red'],
    );

    $key = CacheKey::fromRequest($request);

    expect($key->method)->toBe('GET')
        ->and($key->path)->toBe('/products')
        ->and($key->query)->toBe('color=red');
});

it('normalizes query string to sorted key-value pairs', function (): void {
    $request = new Request(
        server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/products?b=2&a=1'],
        query: ['b' => '2', 'a' => '1'],
    );

    $key = CacheKey::fromRequest($request);

    expect($key->query)->toBe('a=1&b=2');
});

it('produces an empty query for requests without query parameters', function (): void {
    $request = new Request(
        server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/products'],
        query: [],
    );

    $key = CacheKey::fromRequest($request);

    expect($key->query)->toBe('');
});

it('returns different hashes for different methods on the same path', function (): void {
    $getRequest = new Request(
        server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/products'],
        query: [],
    );

    $postRequest = new Request(
        server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/products'],
        query: [],
    );

    $getKey = CacheKey::fromRequest($getRequest);
    $postKey = CacheKey::fromRequest($postRequest);

    expect($getKey->hash())->not->toBe($postKey->hash());
});

it('exposes a public static normalizeQuery helper that sorts query parameters by key', function (): void {
    $normalized = CacheKey::normalizeQuery('b=2&a=1');

    expect($normalized)->toBe('a=1&b=2');
});

it('returns an empty string from normalizeQuery when given an empty string', function (): void {
    expect(CacheKey::normalizeQuery(''))->toBe('');
});

it('preserves query parameter values verbatim during normalization (does not URL-decode)', function (): void {
    $normalized = CacheKey::normalizeQuery('b=hello+world&a=foo%20bar');

    expect($normalized)->toBe('a=foo%20bar&b=hello%2Bworld');
});

it('returns the same hash for two equivalent keys with different query orderings', function (): void {
    $request1 = new Request(
        server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/products?b=2&a=1'],
        query: ['b' => '2', 'a' => '1'],
    );

    $request2 = new Request(
        server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/products?a=1&b=2'],
        query: ['a' => '1', 'b' => '2'],
    );

    $key1 = CacheKey::fromRequest($request1);
    $key2 = CacheKey::fromRequest($request2);

    expect($key1->hash())->toBe($key2->hash());
});
