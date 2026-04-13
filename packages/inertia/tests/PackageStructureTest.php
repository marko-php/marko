<?php

declare(strict_types=1);

use Marko\Inertia\Interfaces\ComponentResolverInterface;
use Marko\Inertia\Interfaces\InertiaInterface;
use Marko\Inertia\Props\PropsResolver;
use Marko\Inertia\Response\ResponseFactory;

it('creates valid package scaffolding with composer.json, module.php, src, tests, config, resources, and docs metadata', function (): void {
    $packageRoot = dirname(__DIR__);

    expect(file_exists($packageRoot . '/composer.json'))->toBeTrue()
        ->and(file_exists($packageRoot . '/README.md'))->toBeTrue()
        ->and(file_exists($packageRoot . '/module.php'))->toBeTrue()
        ->and(file_exists($packageRoot . '/.gitattributes'))->toBeTrue()
        ->and(is_dir($packageRoot . '/src'))->toBeTrue()
        ->and(is_dir($packageRoot . '/tests'))->toBeTrue()
        ->and(is_dir($packageRoot . '/config'))->toBeTrue()
        ->and(file_exists($packageRoot . '/config/inertia.php'))->toBeTrue()
        ->and(is_dir($packageRoot . '/resources'))->toBeTrue()
        ->and(file_exists($packageRoot . '/resources/js/client.ts'))->toBeTrue();
});

it('has a valid composer.json for marko/inertia', function (): void {
    $composer = json_decode(file_get_contents(dirname(__DIR__) . '/composer.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($composer['name'])->toBe('marko/inertia')
        ->and($composer['type'])->toBe('marko-module')
        ->and($composer['license'])->toBe('MIT')
        ->and($composer['require']['php'])->toBe('^8.5')
        ->and($composer['require']['marko/config'])->toBe('self.version')
        ->and($composer['require']['marko/core'])->toBe('self.version')
        ->and($composer['require']['marko/routing'])->toBe('self.version')
        ->and($composer['require']['marko/session'])->toBe('self.version')
        ->and($composer['require']['marko/vite'])->toBe('self.version')
        ->and($composer['autoload']['psr-4']['Marko\\Inertia\\'])->toBe('src/')
        ->and($composer['autoload-dev']['psr-4']['Marko\\Inertia\\Tests\\'])->toBe('tests/');
});

it('has module.php with bindings for the inertia services', function (): void {
    $module = require dirname(__DIR__) . '/module.php';

    expect($module)->toBeArray()
        ->and($module)->toHaveKey('bindings')
        ->and($module['bindings'])->toHaveKey(InertiaInterface::class)
        ->and($module['bindings'])->toHaveKey(ComponentResolverInterface::class)
        ->and($module['singletons'])->toContain(PropsResolver::class)
        ->and($module['singletons'])->not->toContain(ResponseFactory::class)
        ->and($module['singletons'])->not->toContain(\Marko\Inertia\Inertia::class);
});

it('publishes shared client helpers for resolving and parsing page component names', function (): void {
    $client = (string) file_get_contents(dirname(__DIR__) . '/resources/js/client.ts');

    expect($client)->toContain('export async function resolveMarkoPageComponent(')
        ->and($client)->toContain('export function parseMarkoPageComponent(')
        ->and($client)->toContain('export type MarkoParsedPageComponent =');
});
