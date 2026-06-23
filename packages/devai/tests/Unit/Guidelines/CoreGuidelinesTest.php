<?php

declare(strict_types=1);

$coreGuidelines = file_get_contents(dirname(__DIR__, 3) . '/resources/ai/guidelines/core.md');

it('does not reference any dot claude path in the rendered core guidelines', function () use ($coreGuidelines): void {
    expect($coreGuidelines)->not->toContain('.claude/');
});

it('retains the baseline coding rules in the core guidelines', function () use ($coreGuidelines): void {
    expect($coreGuidelines)->toContain('Strict Types')
        ->and($coreGuidelines)->toContain('Constructor Property Promotion')
        ->and($coreGuidelines)->toContain('No Final Classes')
        ->and($coreGuidelines)->toContain('No Magic Methods')
        ->and($coreGuidelines)->toContain('Type Declarations')
        ->and($coreGuidelines)->toContain('Loud Errors');
});

it('includes the no-traits rule', function () use ($coreGuidelines): void {
    expect($coreGuidelines)->toContain('traits');
});

it('includes the interface-over-driver rule', function () use ($coreGuidelines): void {
    expect($coreGuidelines)->toContain('interface')
        ->and($coreGuidelines)->toContain('driver');
});

it('includes the constructor-injection rule', function () use ($coreGuidelines): void {
    expect($coreGuidelines)->toContain('Constructor')
        ->and($coreGuidelines)->toContain('Container::get');
});

it('includes the config-is-source-of-truth rule', function () use ($coreGuidelines): void {
    expect($coreGuidelines)->toContain('config')
        ->and($coreGuidelines)->toContain('ConfigNotFoundException');
});

it('points to the search_docs mcp tool for deeper documentation', function () use ($coreGuidelines): void {
    expect($coreGuidelines)->toContain('search_docs');
});

it('notes that the LSP enforces the lintable formatting rules', function () use ($coreGuidelines): void {
    expect($coreGuidelines)->toContain('LSP');
});

it('stays under a terse line budget (no wholesale code-standards copy)', function () use ($coreGuidelines): void {
    $lineCount = substr_count($coreGuidelines, "\n") + 1;
    expect($lineCount)->toBeLessThanOrEqual(60);
});
