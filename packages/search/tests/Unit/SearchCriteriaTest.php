<?php

declare(strict_types=1);

use Marko\Search\Value\FilterOperator;
use Marko\Search\Value\SearchCriteria;
use Marko\Search\Value\SearchFilter;

it('defines SearchCriteria value object with query, filters, sorting, and pagination', function (): void {
    $criteria = SearchCriteria::create('hello world');

    expect($criteria->query)->toBe('hello world')
        ->and($criteria->filters)->toBeEmpty()
        ->and($criteria->sortBy)->toBe('')
        ->and($criteria->sortDirection)->toBe('asc')
        ->and($criteria->page)->toBe(1)
        ->and($criteria->perPage)->toBe(15);
});

it('creates SearchCriteria with default empty query', function (): void {
    $criteria = SearchCriteria::create();

    expect($criteria->query)->toBe('');
});

it('builds SearchCriteria with fluent filter builder', function (): void {
    $filter = new SearchFilter(
        field: 'status',
        operator: FilterOperator::Equals,
        value: 'active',
    );

    $criteria = SearchCriteria::create('test')
        ->withFilter($filter);

    expect($criteria->filters)->toHaveCount(1)
        ->and($criteria->filters[0])->toBe($filter);
});

it('builds SearchCriteria with fluent sort builder', function (): void {
    $criteria = SearchCriteria::create('test')
        ->withSort('title', 'desc');

    expect($criteria->sortBy)->toBe('title')
        ->and($criteria->sortDirection)->toBe('desc');
});

it('builds SearchCriteria with fluent page builder', function (): void {
    $criteria = SearchCriteria::create('test')
        ->withPage(3);

    expect($criteria->page)->toBe(3);
});

it('builds SearchCriteria with fluent perPage builder', function (): void {
    $criteria = SearchCriteria::create('test')
        ->withPerPage(25);

    expect($criteria->perPage)->toBe(25);
});

it('SearchCriteria is immutable when using fluent builders', function (): void {
    $original = SearchCriteria::create('original');
    $modified = $original->withPage(5);

    expect($original->page)->toBe(1)
        ->and($modified->page)->toBe(5);
});
