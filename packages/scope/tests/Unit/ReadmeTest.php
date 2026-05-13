<?php

declare(strict_types=1);

it('has a title and one-liner stating the benefit', function (): void {
    $readmePath = dirname(__DIR__, 2) . '/README.md';
    $content = file_get_contents($readmePath);

    expect($content)->toContain('# marko/scope')
        ->and($content)->toContain('Scoped attributes for entities with multi-axis hierarchical fallback');
});

it('has an Installation section with composer command', function (): void {
    $readmePath = dirname(__DIR__, 2) . '/README.md';
    $content = file_get_contents($readmePath);

    expect($content)->toContain('## Installation')
        ->and($content)->toContain('composer require marko/scope');
});

it('references driver packages in the installation instructions', function (): void {
    $readmePath = dirname(__DIR__, 2) . '/README.md';
    $content = file_get_contents($readmePath);

    expect($content)->toContain('scope-mysql')
        ->and($content)->toContain('scope-pgsql');
});

it('has a quick example showing the Scoped attribute, setOverride, and resolved', function (): void {
    $readmePath = dirname(__DIR__, 2) . '/README.md';
    $content = file_get_contents($readmePath);

    expect($content)->toContain('#[Scoped')
        ->and($content)->toContain('setOverride')
        ->and($content)->toContain('resolved(');
});

it('has a quick example using locale scope with a Product entity', function (): void {
    $readmePath = dirname(__DIR__, 2) . '/README.md';
    $content = file_get_contents($readmePath);

    expect($content)->toContain('locale')
        ->and($content)->toContain('Product')
        ->and($content)->toContain('$name');
});

it('has a Documentation link', function (): void {
    $readmePath = dirname(__DIR__, 2) . '/README.md';
    $content = file_get_contents($readmePath);

    expect($content)->toContain('## Documentation');
});

it('documents HasScopes trait as the primary storage option', function (): void {
    $readmePath = dirname(__DIR__, 2) . '/README.md';
    $content = file_get_contents($readmePath);

    expect($content)->toContain('HasScopes')
        ->and($content)->toContain('HasScopesInterface')
        ->and($content)->toContain('use HasScopes');
});

it('documents the companion-class approach as an alternative', function (): void {
    $readmePath = dirname(__DIR__, 2) . '/README.md';
    $content = file_get_contents($readmePath);

    expect($content)->toContain('Alternative')
        ->and($content)->toContain('ScopedOverridesEntity');
});

it('warns against using HasScopes trait and ScopedOverridesEntity extender on the same entity', function (): void {
    $readmePath = dirname(__DIR__, 2) . '/README.md';
    $content = file_get_contents($readmePath);

    expect($content)->toContain('ScopeConfigurationException')
        ->and($content)->toContain('traitAndCompanionConflict');
});

it(
    'documents that trait-based entities are compatible with Repository::insertBatch while companion-based entities are not',
    function (): void {
        $readmePath = dirname(__DIR__, 2) . '/README.md';
        $content = file_get_contents($readmePath);

        expect($content)->toContain('insertBatch')
            ->and($content)->toContain('BatchInsertException');
    },
);
