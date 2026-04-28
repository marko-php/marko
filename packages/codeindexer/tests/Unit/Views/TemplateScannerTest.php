<?php

declare(strict_types=1);

use Marko\CodeIndexer\ValueObject\ModuleInfo;
use Marko\CodeIndexer\ValueObject\TemplateEntry;
use Marko\CodeIndexer\Views\TemplateScanner;

$fixtures = __DIR__ . '/../../Fixtures/TemplateFixtures';

it('discovers templates in resources/views/ across every module', function () use ($fixtures): void {
    $scanner = new TemplateScanner();
    $module = new ModuleInfo(
        name: 'module-a',
        path: $fixtures . '/module-a',
        namespace: 'ModuleA',
    );

    $entries = $scanner->scan($module);

    expect($entries)->toBeArray()
        ->and(count($entries))->toBe(3);
});

it('produces entries keyed by module::template name', function () use ($fixtures): void {
    $scanner = new TemplateScanner();
    $module = new ModuleInfo(
        name: 'module-a',
        path: $fixtures . '/module-a',
        namespace: 'ModuleA',
    );

    $entries = $scanner->scan($module);
    $names = array_map(fn (TemplateEntry $e): string => $e->moduleName, $entries);

    expect($names)->each->toBe('module-a');
});

it('supports nested template names like posts/index', function () use ($fixtures): void {
    $scanner = new TemplateScanner();
    $module = new ModuleInfo(
        name: 'module-a',
        path: $fixtures . '/module-a',
        namespace: 'ModuleA',
    );

    $entries = $scanner->scan($module);
    $templateNames = array_map(fn (TemplateEntry $e): string => $e->templateName, $entries);

    expect(in_array('posts/index', $templateNames, true))->toBeTrue()
        ->and(in_array('posts/show', $templateNames, true))->toBeTrue();
});

it('records absolute file path for each template', function () use ($fixtures): void {
    $scanner = new TemplateScanner();
    $module = new ModuleInfo(
        name: 'module-a',
        path: $fixtures . '/module-a',
        namespace: 'ModuleA',
    );

    $entries = $scanner->scan($module);
    $indexEntry = array_values(
        array_filter($entries, fn (TemplateEntry $e): bool => $e->templateName === 'index'),
    )[0];

    expect($indexEntry->absolutePath)->toBe($fixtures . '/module-a/resources/views/index.latte');
});

it('handles multiple template extensions when configured', function () use ($fixtures): void {
    $scanner = new TemplateScanner(extensions: ['latte', 'twig']);
    $moduleD = new ModuleInfo(
        name: 'module-d',
        path: $fixtures . '/module-d',
        namespace: 'ModuleD',
    );

    $entries = $scanner->scan($moduleD);

    expect(count($entries))->toBe(1)
        ->and($entries[0]->extension)->toBe('twig')
        ->and($entries[0]->templateName)->toBe('page');
});

it('returns empty when a module has no resources/views directory', function () use ($fixtures): void {
    $scanner = new TemplateScanner();
    $module = new ModuleInfo(
        name: 'module-c',
        path: $fixtures . '/module-c',
        namespace: 'ModuleC',
    );

    $entries = $scanner->scan($module);

    expect($entries)->toBe([]);
});
