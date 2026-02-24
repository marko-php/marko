<?php

declare(strict_types=1);

use Marko\Pagination\Contracts\CursorPaginatorInterface;
use Marko\Pagination\Cursor;
use Marko\Pagination\CursorPaginator;
use Marko\Pagination\Exceptions\PaginationException;

it('implements CursorPaginatorInterface', function () {
    $paginator = new CursorPaginator(
        items: ['a', 'b'],
        perPage: 10,
    );

    expect($paginator)->toBeInstanceOf(CursorPaginatorInterface::class);
});

it('creates CursorPaginator with items, perPage, and cursor objects', function () {
    $currentCursor = new Cursor(['id' => 10]);
    $nextCursor = new Cursor(['id' => 20]);
    $prevCursor = new Cursor(['id' => 1]);

    $paginator = new CursorPaginator(
        items: ['a', 'b', 'c'],
        perPage: 10,
        cursor: $currentCursor,
        nextCursor: $nextCursor,
        previousCursor: $prevCursor,
    );

    expect($paginator->items())->toBe(['a', 'b', 'c'])
        ->and($paginator->perPage())->toBe(10)
        ->and($paginator->cursor())->toBe($currentCursor)
        ->and($paginator->nextCursor())->toBe($nextCursor)
        ->and($paginator->previousCursor())->toBe($prevCursor);
});

it('returns hasMorePages true when nextCursor exists', function () {
    $paginator = new CursorPaginator(
        items: ['a', 'b'],
        perPage: 10,
        nextCursor: new Cursor(['id' => 42]),
    );

    expect($paginator->hasMorePages())->toBeTrue();
});

it('returns hasMorePages false when nextCursor is null', function () {
    $paginator = new CursorPaginator(
        items: ['a', 'b'],
        perPage: 10,
        nextCursor: null,
    );

    expect($paginator->hasMorePages())->toBeFalse();
});

it('returns current cursor, next cursor, and previous cursor', function () {
    $current = new Cursor(['id' => 5]);
    $next = new Cursor(['id' => 15]);
    $prev = new Cursor(['id' => 1]);

    $paginator = new CursorPaginator(
        items: ['a'],
        perPage: 10,
        cursor: $current,
        nextCursor: $next,
        previousCursor: $prev,
    );

    expect($paginator->cursor())->toBe($current)
        ->and($paginator->nextCursor())->toBe($next)
        ->and($paginator->previousCursor())->toBe($prev);
});

it('handles first page with null current cursor and null previous cursor', function () {
    $paginator = new CursorPaginator(
        items: ['a', 'b', 'c'],
        perPage: 10,
        cursor: null,
        nextCursor: new Cursor(['id' => 3]),
        previousCursor: null,
    );

    expect($paginator->cursor())->toBeNull()
        ->and($paginator->previousCursor())->toBeNull()
        ->and($paginator->nextCursor())->not->toBeNull();
});

it('handles last page with null next cursor', function () {
    $paginator = new CursorPaginator(
        items: ['a', 'b'],
        perPage: 10,
        cursor: new Cursor(['id' => 90]),
        nextCursor: null,
        previousCursor: new Cursor(['id' => 80]),
    );

    expect($paginator->nextCursor())->toBeNull()
        ->and($paginator->hasMorePages())->toBeFalse()
        ->and($paginator->previousCursor())->not->toBeNull();
});

it('throws PaginationException for invalid perPage value', function () {
    new CursorPaginator(
        items: [],
        perPage: 0,
    );
})->throws(PaginationException::class);

it('throws PaginationException for negative perPage value', function () {
    new CursorPaginator(
        items: [],
        perPage: -5,
    );
})->throws(PaginationException::class);

it('works correctly with empty items array', function () {
    $paginator = new CursorPaginator(
        items: [],
        perPage: 10,
        cursor: new Cursor(['id' => 100]),
        nextCursor: null,
        previousCursor: new Cursor(['id' => 90]),
    );

    expect($paginator->items())->toBe([])
        ->and($paginator->hasMorePages())->toBeFalse();
});
