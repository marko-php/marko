<?php

declare(strict_types=1);

it('has a title and one-liner stating PostgreSQL jsonb support for marko/scope', function (): void {
    $readmePath = dirname(__DIR__, 2) . '/README.md';
    $content = file_get_contents($readmePath);

    expect($content)->toContain('# marko/scope-pgsql')
        ->and($content)->toContain('PostgreSQL')
        ->and($content)->toContain('jsonb')
        ->and($content)->toContain('marko/scope');
});

it('has an Installation section with composer command', function (): void {
    $readmePath = dirname(__DIR__, 2) . '/README.md';
    $content = file_get_contents($readmePath);

    expect($content)->toContain('## Installation')
        ->and($content)->toContain('composer require marko/scope-pgsql');
});

it('has a quick example showing scoped ORDER BY with ScopedOrderByFactory', function (): void {
    $readmePath = dirname(__DIR__, 2) . '/README.md';
    $content = file_get_contents($readmePath);

    expect($content)->toContain('ScopedOrderByFactory')
        ->and($content)->toContain('matching');
});

it('shows emitted SQL using the JSONB operator', function (): void {
    $readmePath = dirname(__DIR__, 2) . '/README.md';
    $content = file_get_contents($readmePath);

    expect($content)->toContain('jsonb')
        ->and($content)->toContain("->>")
        ->and($content)->toContain('COALESCE');
});

it('has a Documentation link', function (): void {
    $readmePath = dirname(__DIR__, 2) . '/README.md';
    $content = file_get_contents($readmePath);

    expect($content)->toContain('## Documentation');
});
