<?php

declare(strict_types=1);

use Marko\Pagination\Contracts\PaginatorInterface;
use Marko\Pagination\Exceptions\PaginationException;
use Marko\Pagination\OffsetPaginator;

it('implements PaginatorInterface', function () {
    $paginator = new OffsetPaginator(
        items: ['a', 'b', 'c'],
        total: 50,
        perPage: 15,
        currentPage: 1,
    );

    expect($paginator)->toBeInstanceOf(PaginatorInterface::class);
});

it('creates OffsetPaginator with items, total, perPage, and currentPage', function () {
    $items = ['a', 'b', 'c'];
    $paginator = new OffsetPaginator(
        items: $items,
        total: 50,
        perPage: 15,
        currentPage: 2,
    );

    expect($paginator->items())->toBe($items)
        ->and($paginator->total())->toBe(50)
        ->and($paginator->perPage())->toBe(15)
        ->and($paginator->currentPage())->toBe(2);
});

it('calculates lastPage from total and perPage', function () {
    $paginator = new OffsetPaginator(
        items: [],
        total: 150,
        perPage: 15,
        currentPage: 1,
    );

    expect($paginator->lastPage())->toBe(10);
});

it('calculates lastPage with ceiling for non-even division', function () {
    $paginator = new OffsetPaginator(
        items: [],
        total: 151,
        perPage: 15,
        currentPage: 1,
    );

    expect($paginator->lastPage())->toBe(11);
});

it('returns hasMorePages true when currentPage is less than lastPage', function () {
    $paginator = new OffsetPaginator(
        items: ['a'],
        total: 30,
        perPage: 10,
        currentPage: 2,
    );

    expect($paginator->hasMorePages())->toBeTrue();
});

it('returns hasMorePages false when on lastPage', function () {
    $paginator = new OffsetPaginator(
        items: ['a'],
        total: 30,
        perPage: 10,
        currentPage: 3,
    );

    expect($paginator->hasMorePages())->toBeFalse();
});

it('returns previousPage as null on first page', function () {
    $paginator = new OffsetPaginator(
        items: ['a'],
        total: 30,
        perPage: 10,
        currentPage: 1,
    );

    expect($paginator->previousPage())->toBeNull();
});

it('returns previousPage as currentPage minus 1', function () {
    $paginator = new OffsetPaginator(
        items: ['a'],
        total: 30,
        perPage: 10,
        currentPage: 3,
    );

    expect($paginator->previousPage())->toBe(2);
});

it('returns nextPage as null on last page', function () {
    $paginator = new OffsetPaginator(
        items: ['a'],
        total: 30,
        perPage: 10,
        currentPage: 3,
    );

    expect($paginator->nextPage())->toBeNull();
});

it('returns nextPage as currentPage plus 1', function () {
    $paginator = new OffsetPaginator(
        items: ['a'],
        total: 30,
        perPage: 10,
        currentPage: 1,
    );

    expect($paginator->nextPage())->toBe(2);
});

it('handles empty result set with zero total', function () {
    $paginator = new OffsetPaginator(
        items: [],
        total: 0,
        perPage: 15,
        currentPage: 1,
    );

    expect($paginator->lastPage())->toBe(1)
        ->and($paginator->hasMorePages())->toBeFalse()
        ->and($paginator->previousPage())->toBeNull()
        ->and($paginator->nextPage())->toBeNull()
        ->and($paginator->items())->toBe([]);
});

it('handles single-page result where total equals perPage', function () {
    $paginator = new OffsetPaginator(
        items: range(1, 15),
        total: 15,
        perPage: 15,
        currentPage: 1,
    );

    expect($paginator->lastPage())->toBe(1)
        ->and($paginator->hasMorePages())->toBeFalse()
        ->and($paginator->previousPage())->toBeNull()
        ->and($paginator->nextPage())->toBeNull();
});

it('handles single item result', function () {
    $paginator = new OffsetPaginator(
        items: ['only'],
        total: 1,
        perPage: 15,
        currentPage: 1,
    );

    expect($paginator->lastPage())->toBe(1)
        ->and($paginator->hasMorePages())->toBeFalse();
});

it('throws PaginationException for zero page number', function () {
    new OffsetPaginator(
        items: [],
        total: 10,
        perPage: 5,
        currentPage: 0,
    );
})->throws(PaginationException::class);

it('throws PaginationException for negative page number', function () {
    new OffsetPaginator(
        items: [],
        total: 10,
        perPage: 5,
        currentPage: -1,
    );
})->throws(PaginationException::class);

it('throws PaginationException for zero perPage', function () {
    new OffsetPaginator(
        items: [],
        total: 10,
        perPage: 0,
        currentPage: 1,
    );
})->throws(PaginationException::class);

it('throws PaginationException for negative perPage', function () {
    new OffsetPaginator(
        items: [],
        total: 10,
        perPage: -5,
        currentPage: 1,
    );
})->throws(PaginationException::class);
