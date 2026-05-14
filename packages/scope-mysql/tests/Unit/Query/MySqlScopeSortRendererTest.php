<?php

declare(strict_types=1);

namespace Marko\Scope\MySql\Tests\Unit\Query;

use Marko\Database\Exceptions\InvalidColumnException;
use Marko\Scope\MySql\Query\MySqlScopeSortRenderer;
use Marko\Scope\Query\ScopeSortExpression;

it('renders a single-axis sort as COALESCE over JSON paths and the fallback column', function (): void {
    $renderer = new MySqlScopeSortRenderer();

    $expression = new ScopeSortExpression(
        property: 'price',
        column: 'price',
        paths: [
            ['axis' => 'store', 'path' => 'en'],
            ['axis' => 'store', 'path' => ''],
        ],
        direction: 'asc',
    );

    $sql = $renderer->render($expression);

    expect($sql)->toBe(
        'COALESCE(JSON_UNQUOTE(JSON_EXTRACT(`scopes`, \'$."store:en".price\')), JSON_UNQUOTE(JSON_EXTRACT(`scopes`, \'$."store:".price\')), `price`)',
    );
});

it('embeds path segments containing dots correctly into MySQL JSON paths (e.g. eu.de)', function (): void {
    $renderer = new MySqlScopeSortRenderer();

    $expression = new ScopeSortExpression(
        property: 'name',
        column: 'name',
        paths: [
            ['axis' => 'geo', 'path' => 'eu.de'],
        ],
        direction: 'asc',
    );

    $sql = $renderer->render($expression);

    expect($sql)->toBe(
        'COALESCE(JSON_UNQUOTE(JSON_EXTRACT(`scopes`, \'$."geo:eu.de".name\')), `name`)',
    );
});

it('falls back to plain column expression when the expression has no axis paths', function (): void {
    $renderer = new MySqlScopeSortRenderer();

    $expression = new ScopeSortExpression(
        property: 'price',
        column: 'price',
        paths: [],
        direction: 'asc',
    );

    $sql = $renderer->render($expression);

    expect($sql)->toBe('`price`');
});

it('returns just the expression without direction so the query builder can append it', function (): void {
    $renderer = new MySqlScopeSortRenderer();

    $asc = new ScopeSortExpression(
        property: 'price',
        column: 'price',
        paths: [['axis' => 'store', 'path' => 'en']],
        direction: 'asc',
    );

    $desc = new ScopeSortExpression(
        property: 'price',
        column: 'price',
        paths: [['axis' => 'store', 'path' => 'en']],
        direction: 'desc',
    );

    expect($renderer->render($asc))->not->toEndWith(' ASC')
        ->and($renderer->render($desc))->not->toEndWith(' DESC');
});

it('validates fallback column, property, and json column identifiers against the safe pattern', function (): void {
    $renderer = new MySqlScopeSortRenderer();

    expect(fn () => $renderer->render(new ScopeSortExpression(
        property: 'price',
        column: 'bad column',
        paths: [],
        direction: 'asc',
    )))->toThrow(InvalidColumnException::class)
        ->and(fn () => $renderer->render(new ScopeSortExpression(
            property: 'bad property',
            column: 'price',
            paths: [],
            direction: 'asc',
        )))->toThrow(InvalidColumnException::class)
        ->and(fn () => $renderer->render(new ScopeSortExpression(
            property: 'price',
            column: 'price',
            paths: [],
            direction: 'asc',
            jsonColumn: 'bad column',
        )))->toThrow(InvalidColumnException::class);
});

it('composes the JSON key from already-validated axis name and path segments', function (): void {
    $renderer = new MySqlScopeSortRenderer();

    $expression = new ScopeSortExpression(
        property: 'name',
        column: 'name',
        paths: [
            ['axis' => 'geo', 'path' => 'eu'],
        ],
        direction: 'asc',
    );

    $sql = $renderer->render($expression);

    expect($sql)->toContain('"geo:eu"');
});

it('renders a multi-axis sort with axes in declared priority order', function (): void {
    $renderer = new MySqlScopeSortRenderer();

    $expression = new ScopeSortExpression(
        property: 'price',
        column: 'price',
        paths: [
            ['axis' => 'store', 'path' => 'en'],
            ['axis' => 'store', 'path' => ''],
            ['axis' => 'geo', 'path' => 'de'],
            ['axis' => 'geo', 'path' => ''],
        ],
        direction: 'asc',
    );

    $sql = $renderer->render($expression);

    expect($sql)->toBe(
        'COALESCE('
        . 'JSON_UNQUOTE(JSON_EXTRACT(`scopes`, \'$."store:en".price\')), '
        . 'JSON_UNQUOTE(JSON_EXTRACT(`scopes`, \'$."store:".price\')), '
        . 'JSON_UNQUOTE(JSON_EXTRACT(`scopes`, \'$."geo:de".price\')), '
        . 'JSON_UNQUOTE(JSON_EXTRACT(`scopes`, \'$."geo:".price\')), '
        . '`price`)',
    );
});
