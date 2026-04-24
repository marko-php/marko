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
    expect($composer['suggest']['codewithkyrian/transformers-php'])->toContain('^0.5');
});

it('has module.php binding DocsSearchInterface to VecSearch', function (): void {
    $path = dirname(__DIR__, 2) . '/module.php';
    expect(file_exists($path))->toBeTrue();

    $module = require $path;

    expect($module)->toBeArray();
    expect($module['bindings'])->toBeArray();
    expect(array_key_exists(DocsSearchInterface::class, $module['bindings']))->toBeTrue();
    expect($module['bindings'][DocsSearchInterface::class])->toBe(VecSearch::class);
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
