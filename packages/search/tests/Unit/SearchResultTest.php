<?php

declare(strict_types=1);

use Marko\Search\Value\SearchResult;

it('defines SearchResult value object with items, total, and query metadata', function (): void {
    $result = new SearchResult(
        items: ['item1', 'item2'],
        total: 100,
        query: 'hello',
        page: 1,
        perPage: 15,
    );

    expect($result->items)->toBe(['item1', 'item2'])
        ->and($result->total)->toBe(100)
        ->and($result->query)->toBe('hello')
        ->and($result->page)->toBe(1)
        ->and($result->perPage)->toBe(15);
});

it('calculates total pages correctly', function (): void {
    $result = new SearchResult(
        items: [],
        total: 100,
        query: 'test',
        page: 1,
        perPage: 15,
    );

    expect($result->totalPages())->toBe(7);
});

it('returns true for isEmpty when no items', function (): void {
    $result = new SearchResult(
        items: [],
        total: 0,
        query: 'test',
        page: 1,
        perPage: 15,
    );

    expect($result->isEmpty())->toBeTrue();
});

it('returns false for isEmpty when has items', function (): void {
    $result = new SearchResult(
        items: ['item'],
        total: 1,
        query: 'test',
        page: 1,
        perPage: 15,
    );

    expect($result->isEmpty())->toBeFalse();
});
