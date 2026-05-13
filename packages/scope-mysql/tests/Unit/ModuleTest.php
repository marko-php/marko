<?php

declare(strict_types=1);

use Marko\Scope\MySql\Query\MySqlScopeSortRenderer;
use Marko\Scope\Query\ScopeSortRendererInterface;

it('returns an array with bindings key', function (): void {
    $module = require dirname(__DIR__, 2) . '/module.php';

    expect($module)->toBeArray()
        ->and($module)->toHaveKey('bindings');
});

it('binds ScopeSortRendererInterface to MySqlScopeSortRenderer', function (): void {
    $module = require dirname(__DIR__, 2) . '/module.php';

    expect($module['bindings'])->toHaveKey(ScopeSortRendererInterface::class)
        ->and($module['bindings'][ScopeSortRendererInterface::class])->toBe(MySqlScopeSortRenderer::class);
});

it('does not re-bind marko/scope interfaces', function (): void {
    $module = require dirname(__DIR__, 2) . '/module.php';

    $scopeInterfaces = array_filter(
        array_keys($module['bindings']),
        fn (string $key): bool => str_starts_with(
            $key,
            'Marko\\Scope\\'
        ) && $key !== ScopeSortRendererInterface::class,
    );

    expect($scopeInterfaces)->toBeEmpty();
});
