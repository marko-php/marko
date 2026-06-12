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

it('normalizes both plus-encoded and percent-encoded spaces to RFC3986 percent-encoding', function (): void {
    $normalized = CacheKey::normalizeQuery('b=hello+world&a=foo%20bar');

    expect($normalized)->toBe('a=foo%20bar&b=hello%20world');
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

it(
    'produces the same cache key hash for a URL with a space whether stored from a request or normalized for purge',
    function (): void {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/search?q=hello+world'],
            query: ['q' => 'hello world'],
        );

        $storedKey = CacheKey::fromRequest($request);
        $purgeQuery = CacheKey::normalizeQuery('q=hello+world');
        $purgeKey = new CacheKey(method: 'GET', path: '/search', query: $purgeQuery);

        expect($storedKey->hash())->toBe($purgeKey->hash());
    },
);

it(
    'produces the same cache key hash for a URL with a literal plus whether stored or normalized for purge',
    function (): void {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/search?q=a%2Bb'],
            query: ['q' => 'a+b'],
        );

        $storedKey = CacheKey::fromRequest($request);
        $purgeQuery = CacheKey::normalizeQuery('q=a%2Bb');
        $purgeKey = new CacheKey(method: 'GET', path: '/search', query: $purgeQuery);

        expect($storedKey->hash())->toBe($purgeKey->hash());
    },
);

it('normalizes an empty query to an empty string in both store and purge paths', function (): void {
    $request = new Request(
        server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/page'],
        query: [],
    );

    $storedKey = CacheKey::fromRequest($request);
    $purgeQuery = CacheKey::normalizeQuery('');

    expect($storedKey->query)->toBe('')
        ->and($purgeQuery)->toBe('');
});

it('produces matching hashes for a multi-parameter query across store and purge', function (): void {
    $request = new Request(
        server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/search?z=3&a=hello+world&m=foo%20bar'],
        query: ['z' => '3', 'a' => 'hello world', 'm' => 'foo bar'],
    );

    $storedKey = CacheKey::fromRequest($request);
    $purgeQuery = CacheKey::normalizeQuery('z=3&a=hello+world&m=foo%20bar');
    $purgeKey = new CacheKey(method: 'GET', path: '/search', query: $purgeQuery);

    expect($storedKey->hash())->toBe($purgeKey->hash());
});

it('sorts query parameters so key ordering does not affect the hash', function (): void {
    $request1 = new Request(
        server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/page?z=3&a=1&m=2'],
        query: ['z' => '3', 'a' => '1', 'm' => '2'],
    );

    $request2 = new Request(
        server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/page?m=2&z=3&a=1'],
        query: ['m' => '2', 'z' => '3', 'a' => '1'],
    );

    $normalizedForward = CacheKey::normalizeQuery('z=3&a=1&m=2');
    $normalizedReverse = CacheKey::normalizeQuery('a=1&m=2&z=3');

    expect(CacheKey::fromRequest($request1)->hash())->toBe(CacheKey::fromRequest($request2)->hash())
        ->and($normalizedForward)->toBe($normalizedReverse)
        ->and($normalizedForward)->toBe('a=1&m=2&z=3');
});
