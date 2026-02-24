<?php

declare(strict_types=1);

use Marko\Pagination\Cursor;
use Marko\Pagination\CursorPaginator;
use Marko\Pagination\OffsetPaginator;

it('serializes OffsetPaginator to array with items, meta, and links', function () {
    $paginator = new OffsetPaginator(
        items: ['a', 'b', 'c'],
        total: 150,
        perPage: 15,
        currentPage: 3,
    );

    $result = $paginator->toArray();

    expect($result)->toHaveKeys(['items', 'meta', 'links']);
});

it('includes total, per_page, current_page, last_page in offset meta', function () {
    $paginator = new OffsetPaginator(
        items: ['a', 'b', 'c'],
        total: 150,
        perPage: 15,
        currentPage: 3,
    );

    $result = $paginator->toArray();

    expect($result['meta'])->toBe([
        'total' => 150,
        'per_page' => 15,
        'current_page' => 3,
        'last_page' => 10,
    ]);
});

it('includes previous and next page numbers in offset links', function () {
    $paginator = new OffsetPaginator(
        items: ['a', 'b', 'c'],
        total: 150,
        perPage: 15,
        currentPage: 3,
    );

    $result = $paginator->toArray();

    expect($result['links'])->toBe([
        'previous' => 2,
        'next' => 4,
    ]);
});

it('returns null offset links when no previous or next page exists', function () {
    $paginator = new OffsetPaginator(
        items: ['a'],
        total: 1,
        perPage: 15,
        currentPage: 1,
    );

    $result = $paginator->toArray();

    expect($result['links'])->toBe([
        'previous' => null,
        'next' => null,
    ]);
});

it('passes items through as-is in OffsetPaginator toArray', function () {
    $items = [
        ['id' => 1, 'name' => 'Alice'],
        ['id' => 2, 'name' => 'Bob'],
    ];

    $paginator = new OffsetPaginator(
        items: $items,
        total: 50,
        perPage: 10,
        currentPage: 1,
    );

    $result = $paginator->toArray();

    expect($result['items'])->toBe($items);
});

it('serializes CursorPaginator to array with items, meta, and links', function () {
    $paginator = new CursorPaginator(
        items: ['a', 'b', 'c'],
        perPage: 15,
        cursor: new Cursor(['id' => 10]),
        nextCursor: new Cursor(['id' => 42]),
        previousCursor: new Cursor(['id' => 1]),
    );

    $result = $paginator->toArray();

    expect($result)->toHaveKeys(['items', 'meta', 'links']);
});

it('includes per_page and has_more in cursor meta', function () {
    $paginator = new CursorPaginator(
        items: ['a', 'b'],
        perPage: 15,
        nextCursor: new Cursor(['id' => 42]),
    );

    $result = $paginator->toArray();

    expect($result['meta'])->toBe([
        'per_page' => 15,
        'has_more' => true,
    ]);
});

it('includes encoded cursor strings in cursor links', function () {
    $nextCursor = new Cursor(['id' => 42]);
    $prevCursor = new Cursor(['id' => 1]);

    $paginator = new CursorPaginator(
        items: ['a', 'b'],
        perPage: 15,
        nextCursor: $nextCursor,
        previousCursor: $prevCursor,
    );

    $result = $paginator->toArray();

    expect($result['links']['next'])->toBe($nextCursor->encode())
        ->and($result['links']['previous'])->toBe($prevCursor->encode());
});

it('returns null cursor links when no previous or next cursor exists', function () {
    $paginator = new CursorPaginator(
        items: ['a', 'b'],
        perPage: 15,
    );

    $result = $paginator->toArray();

    expect($result['links'])->toBe([
        'previous' => null,
        'next' => null,
    ]);
});

it('includes has_more false in cursor meta when no next cursor', function () {
    $paginator = new CursorPaginator(
        items: ['a', 'b'],
        perPage: 15,
        nextCursor: null,
    );

    $result = $paginator->toArray();

    expect($result['meta']['has_more'])->toBeFalse();
});

it('passes items through as-is in CursorPaginator toArray', function () {
    $items = [
        ['id' => 1, 'name' => 'Alice'],
        ['id' => 2, 'name' => 'Bob'],
    ];

    $paginator = new CursorPaginator(
        items: $items,
        perPage: 10,
    );

    $result = $paginator->toArray();

    expect($result['items'])->toBe($items);
});
