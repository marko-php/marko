<?php

declare(strict_types=1);

use Marko\CodeIndexer\Config\ConfigScanner;
use Marko\CodeIndexer\ValueObject\ConfigKeyEntry;
use Marko\CodeIndexer\ValueObject\ModuleInfo;

$fixtures = __DIR__ . '/../../Fixtures/ConfigFixtures';

it('discovers config files in every module config directory', function () use ($fixtures): void {
    $scanner = new ConfigScanner();
    $module = new ModuleInfo('module-a', $fixtures . '/module-a', 'Marko\\ModuleA');

    $entries = $scanner->scan($module);

    expect($entries)->not->toBeEmpty()
        ->and(count($entries))->toBeGreaterThanOrEqual(2);
});

it('records dynamic config files (non-static return) as diagnostics while still indexing known keys', function () use ($fixtures): void {
    $scanner = new ConfigScanner();
    $module = new ModuleInfo('module-c', $fixtures . '/module-c', 'Marko\\ModuleC');

    $entries = $scanner->scan($module);
    $diagnostics = $scanner->diagnostics();

    $keys = array_map(fn (ConfigKeyEntry $e): string => $e->key, $entries);
    $byKey = [];
    foreach ($entries as $entry) {
        $byKey[$entry->key] = $entry;
    }

    // dynamic.php returns a function call — no keys, but diagnostic recorded
    $diagnosticFiles = array_map(fn (array $d): string => basename($d['file']), $diagnostics);
    expect($diagnosticFiles)->toContain('dynamic.php');

    // partial.php has a mix — known keys indexed, dynamic value recorded as type='dynamic'
    expect($keys)->toContain('partial.driver')
        ->and($keys)->toContain('partial.port')
        ->and($keys)->toContain('partial.host')
        ->and($byKey['partial.port']->type)->toBe('dynamic')
        ->and($byKey['partial.port']->defaultValue)->toBeNull();
});

it('does not include or eval config files — reads values via AST only', function (): void {
    $scannerSource = file_get_contents(
        dirname(__DIR__, 3) . '/src/Config/ConfigScanner.php',
    );

    expect(str_contains($scannerSource, 'include '))->toBeFalse()
        ->and(str_contains($scannerSource, 'include_once '))->toBeFalse()
        ->and(str_contains($scannerSource, 'require '))->toBeFalse()
        ->and(str_contains($scannerSource, 'require_once '))->toBeFalse()
        ->and(str_contains($scannerSource, 'eval('))->toBeFalse();
});

it('returns entries namespaced by the module name', function () use ($fixtures): void {
    $scanner = new ConfigScanner();
    $module = new ModuleInfo('module-a', $fixtures . '/module-a', 'Marko\\ModuleA');

    $entries = $scanner->scan($module);

    foreach ($entries as $entry) {
        expect($entry->module)->toBe('module-a');
    }
});

it('ignores config files with syntax errors and records them as warnings', function () use ($fixtures): void {
    $scanner = new ConfigScanner();
    $module = new ModuleInfo('module-d', $fixtures . '/module-d', 'Marko\\ModuleD');

    $entries = $scanner->scan($module);
    $diagnostics = $scanner->diagnostics();

    expect($entries)->toBeEmpty()
        ->and($diagnostics)->not->toBeEmpty()
        ->and($diagnostics[0]['file'])->toEndWith('broken.php')
        ->and($diagnostics[0]['error'])->not->toBeEmpty();
});

it('handles scoped config with default plus scopes cascade', function () use ($fixtures): void {
    $scanner = new ConfigScanner();
    $module = new ModuleInfo('module-b', $fixtures . '/module-b', 'Marko\\ModuleB');

    $entries = $scanner->scan($module);
    $keys = array_map(fn (ConfigKeyEntry $e): string => $e->key, $entries);

    expect($keys)->toContain('scoped.default.driver')
        ->and($keys)->toContain('scoped.default.table')
        ->and($keys)->toContain('scoped.scopes.tenant1.driver')
        ->and($keys)->toContain('scoped.scopes.tenant1.table');
});

it('records source file and line number for each top-level key', function () use ($fixtures): void {
    $scanner = new ConfigScanner();
    $module = new ModuleInfo('module-a', $fixtures . '/module-a', 'Marko\\ModuleA');

    $entries = $scanner->scan($module);
    $byKey = [];
    foreach ($entries as $entry) {
        $byKey[$entry->key] = $entry;
    }

    expect($byKey['mail.driver']->file)->toEndWith('mail.php')
        ->and($byKey['mail.driver']->line)->toBeGreaterThan(0)
        ->and($byKey['cache.driver']->file)->toEndWith('cache.php')
        ->and($byKey['cache.driver']->line)->toBeGreaterThan(0);
});

it('captures scalar default values with their types', function () use ($fixtures): void {
    $scanner = new ConfigScanner();
    $module = new ModuleInfo('module-a', $fixtures . '/module-a', 'Marko\\ModuleA');

    $entries = $scanner->scan($module);
    $byKey = [];
    foreach ($entries as $entry) {
        $byKey[$entry->key] = $entry;
    }

    expect($byKey['mail.driver']->defaultValue)->toBe('smtp')
        ->and($byKey['mail.driver']->type)->toBe('string')
        ->and($byKey['mail.port']->defaultValue)->toBe(587)
        ->and($byKey['mail.port']->type)->toBe('int')
        ->and($byKey['cache.ttl']->defaultValue)->toBe(3600)
        ->and($byKey['cache.ttl']->type)->toBe('int');
});

it('flattens nested arrays to dot-notation keys', function () use ($fixtures): void {
    $scanner = new ConfigScanner();
    $module = new ModuleInfo('module-a', $fixtures . '/module-a', 'Marko\\ModuleA');

    $entries = $scanner->scan($module);
    $keys = array_map(fn (ConfigKeyEntry $e): string => $e->key, $entries);

    expect($keys)->toContain('mail.driver')
        ->and($keys)->toContain('mail.from.address')
        ->and($keys)->toContain('mail.from.name');
});
