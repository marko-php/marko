<?php

declare(strict_types=1);

it('has a title and one-liner stating MySQL and MariaDB support for marko/scope', function (): void {
    $readmePath = dirname(__DIR__, 2) . '/README.md';
    $content = file_get_contents($readmePath);

    expect($content)->toContain('# marko/scope-mysql')
        ->and($content)->toContain('MySQL')
        ->and($content)->toContain('MariaDB')
        ->and($content)->toContain('marko/scope');
});

it('has an Installation section with composer command', function (): void {
    $readmePath = dirname(__DIR__, 2) . '/README.md';
    $content = file_get_contents($readmePath);

    expect($content)->toContain('## Installation')
        ->and($content)->toContain('composer require marko/scope-mysql');
});

it('has a quick example showing scoped ORDER BY with ScopedOrderByFactory', function (): void {
    $readmePath = dirname(__DIR__, 2) . '/README.md';
    $content = file_get_contents($readmePath);

    expect($content)->toContain('ScopedOrderByFactory')
        ->and($content)->toContain('matching');
});

it('shows emitted SQL using JSON_UNQUOTE and JSON_EXTRACT', function (): void {
    $readmePath = dirname(__DIR__, 2) . '/README.md';
    $content = file_get_contents($readmePath);

    expect($content)->toContain('JSON_UNQUOTE')
        ->and($content)->toContain('JSON_EXTRACT');
});

it('has a Documentation link', function (): void {
    $readmePath = dirname(__DIR__, 2) . '/README.md';
    $content = file_get_contents($readmePath);

    expect($content)->toContain('## Documentation');
});
