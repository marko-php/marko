<?php

declare(strict_types=1);

use Marko\CodeIndexer\Translations\TranslationScanner;
use Marko\CodeIndexer\ValueObject\ModuleInfo;
use Marko\CodeIndexer\ValueObject\TranslationEntry;

$fixtures = __DIR__ . '/../../Fixtures/TranslationFixtures';

it('discovers translation files across locales in every module', function () use ($fixtures): void {
    $scanner = new TranslationScanner();
    $module = new ModuleInfo('module-blog', $fixtures . '/module-blog', 'Marko\\Blog');

    $entries = $scanner->scan($module);

    expect($entries)->not->toBeEmpty()
        ->and(count($entries))->toBeGreaterThanOrEqual(2);
});

it('flattens nested translation arrays to dot notation', function () use ($fixtures): void {
    $scanner = new TranslationScanner();
    $module = new ModuleInfo('module-blog', $fixtures . '/module-blog', 'Marko\\Blog');

    $entries = $scanner->scan($module);
    $keys = array_map(fn (TranslationEntry $e): string => $e->key, $entries);

    expect($keys)->toContain('messages.welcome')
        ->and($keys)->toContain('messages.posts.title')
        ->and($keys)->toContain('messages.posts.list.empty');
});

it('captures namespace when translations live under a namespaced module', function () use ($fixtures): void {
    $scanner = new TranslationScanner();
    $module = new ModuleInfo('module-blog', $fixtures . '/module-blog', 'Marko\\Blog');

    $entries = $scanner->scan($module);

    foreach ($entries as $entry) {
        expect($entry->namespace)->toBe('module-blog');
    }
});

it('records source file and line for each top-level key', function () use ($fixtures): void {
    $scanner = new TranslationScanner();
    $module = new ModuleInfo('module-blog', $fixtures . '/module-blog', 'Marko\\Blog');

    $entries = $scanner->scan($module);
    $byKey = [];
    foreach ($entries as $entry) {
        $byKey[$entry->key . ':' . $entry->locale] = $entry;
    }

    expect($byKey['messages.welcome:en']->file)->toEndWith('messages.php')
        ->and($byKey['messages.welcome:en']->line)->toBeGreaterThan(0);
});

it('returns empty for modules without resources/translations', function () use ($fixtures): void {
    $scanner = new TranslationScanner();
    $module = new ModuleInfo('module-noop', $fixtures . '/module-noop', 'Marko\\Noop');

    $entries = $scanner->scan($module);

    expect($entries)->toBeEmpty();
});

it('groups entries by locale for fallback-aware completion', function () use ($fixtures): void {
    $scanner = new TranslationScanner();
    $module = new ModuleInfo('module-blog', $fixtures . '/module-blog', 'Marko\\Blog');

    $entries = $scanner->scan($module);

    $enEntries = array_filter($entries, fn (TranslationEntry $e): bool => $e->locale === 'en');
    $esEntries = array_filter($entries, fn (TranslationEntry $e): bool => $e->locale === 'es');

    expect($enEntries)->not->toBeEmpty()
        ->and($esEntries)->not->toBeEmpty();
});
