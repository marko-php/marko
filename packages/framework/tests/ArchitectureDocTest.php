<?php

declare(strict_types=1);

$architecturePath = dirname(__DIR__, 3) . '/.claude/architecture.md';

it('architecture.md contains a section titled Engine-Specific Template Siblings', function () use ($architecturePath) {
    $content = file_get_contents($architecturePath);

    expect($content)->toContain('## Engine-Specific Template Siblings');
});

it('the section is placed between Package Architecture and Dependency Injection', function () use ($architecturePath) {
    $content = file_get_contents($architecturePath);

    $packageArchPos = strpos($content, '## Package Architecture');
    $siblingPos     = strpos($content, '## Engine-Specific Template Siblings');
    $diPos          = strpos($content, '## Dependency Injection');

    expect($packageArchPos)->toBeLessThan($siblingPos)
        ->and($siblingPos)->toBeLessThan($diPos);
});

it('the section explains the marko/{module}-{engine} naming pattern', function () use ($architecturePath) {
    $content = file_get_contents($architecturePath);

    expect($content)->toContain('marko/{module}-{engine}');
});

it('the section documents the templates_for composer-extra key', function () use ($architecturePath) {
    $content = file_get_contents($architecturePath);

    expect($content)->toContain('templates_for');
});

it('the section explains when to use the pattern (reusable UI packages)', function () use ($architecturePath) {
    $content = file_get_contents($architecturePath);

    expect($content)->toContain('reusable')
        ->and($content)->toContain('UI');
});

it('the section explains when NOT to use the pattern (application-specific modules)', function () use ($architecturePath) {
    $content = file_get_contents($architecturePath);

    expect($content)->toContain('application-specific');
});

it('the section mentions the CrossEngineTemplateParityTest as the enforcement mechanism', function () use ($architecturePath) {
    $content = file_get_contents($architecturePath);

    expect($content)->toContain('CrossEngineTemplateParityTest');
});
