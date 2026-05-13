<?php

declare(strict_types=1);

use Marko\Scope\Exceptions\ScopeConfigurationException;
use Marko\Scope\Query\ScopeSortExpression;

it('is a readonly value object', function (): void {
    $reflection = new ReflectionClass(ScopeSortExpression::class);

    expect($reflection->isReadOnly())->toBeTrue();
});

it('validates direction is asc or desc', function (): void {
    expect(fn () => new ScopeSortExpression(
        property: 'price',
        column: 'price',
        paths: [],
        direction: 'invalid',
    ))->toThrow(ScopeConfigurationException::class);
});

it('creates a ScopeSortExpression with property, column, axis walk paths in order, and direction', function (): void {
    $paths = [
        ['axis' => 'store', 'path' => 'store/en'],
        ['axis' => 'store', 'path' => 'store'],
        ['axis' => 'geo', 'path' => 'geo/de'],
        ['axis' => 'geo', 'path' => 'geo'],
    ];

    $expression = new ScopeSortExpression(
        property: 'price',
        column: 'price',
        paths: $paths,
        direction: 'asc',
    );

    expect($expression->property)->toBe('price')
        ->and($expression->column)->toBe('price')
        ->and($expression->paths)->toBe($paths)
        ->and($expression->direction)->toBe('asc');
});
