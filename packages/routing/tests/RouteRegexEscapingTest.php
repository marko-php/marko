<?php

declare(strict_types=1);

use Marko\Routing\MatchedRoute;
use Marko\Routing\RouteCollection;
use Marko\Routing\RouteDefinition;
use Marko\Routing\RouteMatcher;

it('matches a literal dot in a path segment only against a real dot', function (): void {
    $collection = new RouteCollection();
    $route = new RouteDefinition(
        method: 'GET',
        path: '/sitemap.xml',
        controller: 'SitemapController',
        action: 'index',
    );
    $collection->add($route);

    $matcher = new RouteMatcher($collection);

    expect($matcher->match('GET', '/sitemap.xml'))->toBeInstanceOf(MatchedRoute::class);
});

it('does not match a path where a literal dot position contains a different character', function (): void {
    $collection = new RouteCollection();
    $route = new RouteDefinition(
        method: 'GET',
        path: '/sitemap.xml',
        controller: 'SitemapController',
        action: 'index',
    );
    $collection->add($route);

    $matcher = new RouteMatcher($collection);

    expect($matcher->match('GET', '/sitemapXxml'))->toBeNull();
});

it('matches a path containing a hash character without breaking the regex delimiter', function (): void {
    $collection = new RouteCollection();
    $route = new RouteDefinition(
        method: 'GET',
        path: '/tags/c#sharp',
        controller: 'TagController',
        action: 'show',
    );
    $collection->add($route);

    $matcher = new RouteMatcher($collection);

    expect($matcher->match('GET', '/tags/c#sharp'))->toBeInstanceOf(MatchedRoute::class);
});

it('still captures a named parameter for a route with a {param} placeholder', function (): void {
    $collection = new RouteCollection();
    $route = new RouteDefinition(
        method: 'GET',
        path: '/files/{name}.txt',
        controller: 'FileController',
        action: 'show',
    );
    $collection->add($route);

    $matcher = new RouteMatcher($collection);
    $result = $matcher->match('GET', '/files/readme.txt');

    expect($result)->toBeInstanceOf(MatchedRoute::class)
        ->and($result->parameters)->toBe(['name' => 'readme']);
});

it('matches a path containing other regex metacharacters literally', function (): void {
    $collection = new RouteCollection();
    $route = new RouteDefinition(
        method: 'GET',
        path: '/api/v1+json/resource',
        controller: 'ApiController',
        action: 'index',
    );
    $collection->add($route);

    $matcher = new RouteMatcher($collection);

    expect($matcher->match('GET', '/api/v1+json/resource'))->toBeInstanceOf(MatchedRoute::class)
        ->and($matcher->match('GET', '/api/v1Xjson/resource'))->toBeNull();
});

it('rawurldecodes a matched parameter value exactly once', function (): void {
    $collection = new RouteCollection();
    $route = new RouteDefinition(
        method: 'GET',
        path: '/search/{query}',
        controller: 'SearchController',
        action: 'show',
    );
    $collection->add($route);

    $matcher = new RouteMatcher($collection);
    // %20 is a percent-encoded space; the matched value should decode to "hello world" not "hello%20world"
    $result = $matcher->match('GET', '/search/hello%20world');

    expect($result)->toBeInstanceOf(MatchedRoute::class)
        ->and($result->parameters)->toBe(['query' => 'hello world']);
});
