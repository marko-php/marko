<?php

declare(strict_types=1);
use Marko\Docs\Contract\DocsSearchInterface;
use Marko\DocsVec\VecSearch;

it('has composer.json with name marko/docs-vec and required dependencies', function (): void {
    $path = dirname(__DIR__, 2) . '/composer.json';
    expect(file_exists($path))->toBeTrue();

    $composer = json_decode(file_get_contents($path), true);

    expect($composer['name'])->toBe('marko/docs-vec');
    expect($composer['require']['php'])->toBe('^8.5');
    expect($composer['require']['ext-pdo_sqlite'])->toBe('*');
    expect($composer['require']['marko/core'])->toBe('self.version');
    expect($composer['require']['marko/docs'])->toBe('self.version');
    expect($composer['require']['marko/docs-markdown'])->toBe('self.version');
    expect($composer['suggest']['codewithkyrian/transformers'])->toContain('^0.5');
});

it('provides DocsSearchInterface via a singleton factory with empty bindings', function (): void {
    $path = dirname(__DIR__, 2) . '/module.php';
    expect(file_exists($path))->toBeTrue();

    $module = require $path;

    expect($module)->toBeArray();

    // bindings MUST stay empty: also declaring DocsSearchInterface here would
    // register the same interface twice in one module → BindingConflictException.
    expect($module['bindings'])->toBeArray()->toBeEmpty();

    // The interface is provided by the singleton factory instead.
    expect($module['singletons'])->toBeArray();
    expect(array_key_exists(DocsSearchInterface::class, $module['singletons']))->toBeTrue();
    expect($module['singletons'][DocsSearchInterface::class])->toBeInstanceOf(Closure::class);
});

it('has src tests/Unit tests/Feature directories with Pest bootstrap', function (): void {
    $base = dirname(__DIR__, 2);

    expect(is_dir($base . '/src'))->toBeTrue();
    expect(is_dir($base . '/tests/Unit'))->toBeTrue();
    expect(is_dir($base . '/tests/Feature'))->toBeTrue();
    expect(file_exists($base . '/tests/Pest.php'))->toBeTrue();

    $pest = file_get_contents($base . '/tests/Pest.php');
    expect($pest)->toContain('declare(strict_types=1)');
});

it('autoloads cleanly with composer dump-autoload', function (): void {
    expect(class_exists(VecSearch::class))->toBeTrue();
    expect(in_array(DocsSearchInterface::class, class_implements(VecSearch::class)))->toBeTrue();
});

it('documents ONNX model bundle requirements in README placeholder', function (): void {
    $path = dirname(__DIR__, 2) . '/README.md';
    expect(file_exists($path))->toBeTrue();

    $readme = file_get_contents($path);
    expect($readme)->toContain('ONNX');
    expect($readme)->toContain('bge-small-en-v1.5');
    expect($readme)->toContain('docs-vec:download-model');
});
