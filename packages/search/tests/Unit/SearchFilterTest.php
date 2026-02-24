<?php

declare(strict_types=1);

use Marko\Search\Value\FilterOperator;
use Marko\Search\Value\SearchFilter;

it('defines SearchFilter value object with field, operator, and value', function (): void {
    $filter = new SearchFilter(
        field: 'status',
        operator: FilterOperator::Equals,
        value: 'active',
    );

    expect($filter->field)->toBe('status')
        ->and($filter->operator)->toBe(FilterOperator::Equals)
        ->and($filter->value)->toBe('active');
});

it('defines FilterOperator enum with all expected cases', function (): void {
    expect(FilterOperator::Equals->value)->toBe('equals')
        ->and(FilterOperator::NotEquals->value)->toBe('not_equals')
        ->and(FilterOperator::GreaterThan->value)->toBe('greater_than')
        ->and(FilterOperator::LessThan->value)->toBe('less_than')
        ->and(FilterOperator::In->value)->toBe('in')
        ->and(FilterOperator::Like->value)->toBe('like');
});

it('supports mixed value types in SearchFilter', function (): void {
    $stringFilter = new SearchFilter(field: 'name', operator: FilterOperator::Like, value: 'foo%');
    $intFilter = new SearchFilter(field: 'age', operator: FilterOperator::GreaterThan, value: 18);
    $arrayFilter = new SearchFilter(field: 'status', operator: FilterOperator::In, value: ['active', 'pending']);

    expect($stringFilter->value)->toBe('foo%')
        ->and($intFilter->value)->toBe(18)
        ->and($arrayFilter->value)->toBe(['active', 'pending']);
});
