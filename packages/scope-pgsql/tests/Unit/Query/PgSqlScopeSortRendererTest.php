<?php

declare(strict_types=1);

use Marko\Database\Exceptions\InvalidColumnException;
use Marko\Scope\PgSql\Query\PgSqlScopeSortRenderer;
use Marko\Scope\Query\ScopeSortExpression;

it('renders a single-axis sort as COALESCE over jsonb path lookups and the fallback column', function (): void {
    $renderer = new PgSqlScopeSortRenderer();

    $expression = new ScopeSortExpression(
        property: 'price',
        column: 'price',
        paths: [
            ['axis' => 'store', 'path' => 'store'],
        ],
        direction: 'asc',
    );

    $sql = $renderer->render($expression);

    expect($sql)->toBe('COALESCE("scopes"->\'store:store\'->>\'price\', "price") ASC');
});

it('renders a multi-axis sort with axes in declared priority order', function (): void {
    $renderer = new PgSqlScopeSortRenderer();

    $expression = new ScopeSortExpression(
        property: 'price',
        column: 'price',
        paths: [
            ['axis' => 'store', 'path' => 'store.en'],
            ['axis' => 'store', 'path' => 'store'],
            ['axis' => 'geo', 'path' => 'geo.de'],
            ['axis' => 'geo', 'path' => 'geo'],
        ],
        direction: 'asc',
    );

    $sql = $renderer->render($expression);

    expect($sql)->toBe(
        'COALESCE("scopes"->\'store:store.en\'->>\'price\', "scopes"->\'store:store\'->>\'price\', "scopes"->\'geo:geo.de\'->>\'price\', "scopes"->\'geo:geo\'->>\'price\', "price") ASC',
    );
});

it('composes the JSON key from already-validated axis name and path segments', function (): void {
    $renderer = new PgSqlScopeSortRenderer();

    $expression = new ScopeSortExpression(
        property: 'name',
        column: 'name',
        paths: [
            ['axis' => 'locale', 'path' => 'en'],
        ],
        direction: 'asc',
    );

    $sql = $renderer->render($expression);

    expect($sql)->toContain("\"scopes\"->'locale:en'->>'name'");
});

it('validates fallback column, property, and json column identifiers against the safe pattern', function (): void {
    $renderer = new PgSqlScopeSortRenderer();

    expect(fn () => $renderer->render(new ScopeSortExpression(
        property: 'price',
        column: '0invalid',
        paths: [],
        direction: 'asc',
    )))->toThrow(InvalidColumnException::class)
        ->and(fn () => $renderer->render(new ScopeSortExpression(
            property: 'pri ce',
            column: 'price',
            paths: [],
            direction: 'asc',
        )))->toThrow(InvalidColumnException::class)
        ->and(fn () => $renderer->render(new ScopeSortExpression(
            property: 'price',
            column: 'price',
            paths: [],
            direction: 'asc',
            jsonColumn: 'bad-column',
        )))->toThrow(InvalidColumnException::class);
});

it('preserves direction asc or desc in the output', function (): void {
    $renderer = new PgSqlScopeSortRenderer();

    $ascending = new ScopeSortExpression(
        property: 'price',
        column: 'price',
        paths: [['axis' => 'store', 'path' => 'store']],
        direction: 'asc',
    );

    $descending = new ScopeSortExpression(
        property: 'price',
        column: 'price',
        paths: [['axis' => 'store', 'path' => 'store']],
        direction: 'desc',
    );

    expect($renderer->render($ascending))->toEndWith(' ASC')
        ->and($renderer->render($descending))->toEndWith(' DESC');
});

it('falls back to plain ORDER BY column when the expression has no axis paths', function (): void {
    $renderer = new PgSqlScopeSortRenderer();

    $expression = new ScopeSortExpression(
        property: 'price',
        column: 'price',
        paths: [],
        direction: 'asc',
    );

    $sql = $renderer->render($expression);

    expect($sql)->toBe('"price" ASC');
});

it('embeds path segments containing dots correctly into PG jsonb paths (e.g. eu.de)', function (): void {
    $renderer = new PgSqlScopeSortRenderer();

    $expression = new ScopeSortExpression(
        property: 'price',
        column: 'price',
        paths: [
            ['axis' => 'geo', 'path' => 'eu.de'],
        ],
        direction: 'asc',
    );

    $sql = $renderer->render($expression);

    expect($sql)->toBe('COALESCE("scopes"->\'geo:eu.de\'->>\'price\', "price") ASC');
});
