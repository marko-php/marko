<?php

declare(strict_types=1);

it('creates packages/page-cache-entity with a valid composer.json', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';

    expect(file_exists($composerPath))->toBeTrue();

    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer)->toBeArray()
        ->and(json_last_error())->toBe(JSON_ERROR_NONE);
});

it('declares marko/page-cache-entity as the package name', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer)->toHaveKey('name')
        ->and($composer['name'])->toBe('marko/page-cache-entity');
});

it('declares the marko-module type and the extra.marko.module flag', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer)->toHaveKey('type')
        ->and($composer['type'])->toBe('marko-module')
        ->and($composer)->toHaveKey('extra')
        ->and($composer['extra'])->toHaveKey('marko')
        ->and($composer['extra']['marko'])->toHaveKey('module')
        ->and($composer['extra']['marko']['module'])->toBeTrue();
});

it('requires marko/page-cache and marko/database with self.version constraints', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer)->toHaveKey('require')
        ->and($composer['require'])->toHaveKey('marko/page-cache')
        ->and($composer['require']['marko/page-cache'])->toBe('self.version')
        ->and($composer['require'])->toHaveKey('marko/database')
        ->and($composer['require']['marko/database'])->toBe('self.version');
});

it('registers PSR-4 autoload for Marko PageCache Entity namespace pointing at src', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer)->toHaveKey('autoload')
        ->and($composer['autoload'])->toHaveKey('psr-4')
        ->and($composer['autoload']['psr-4'])->toHaveKey('Marko\\PageCache\\Entity\\')
        ->and($composer['autoload']['psr-4']['Marko\\PageCache\\Entity\\'])->toBe('src/');
});

it('ships a module.php returning an array', function (): void {
    $modulePath = dirname(__DIR__) . '/module.php';

    expect(file_exists($modulePath))->toBeTrue();

    $config = require $modulePath;

    expect($config)->toBeArray();
});

it('has a LICENSE file and .gitattributes', function (): void {
    $packageDir = dirname(__DIR__);

    expect(file_exists($packageDir . '/LICENSE'))->toBeTrue()
        ->and(file_exists($packageDir . '/.gitattributes'))->toBeTrue();
});

it('adds packages/page-cache-entity as a path repository in the root composer.json', function (): void {
    $rootComposerPath = dirname(__DIR__, 3) . '/composer.json';
    $composer = json_decode(file_get_contents($rootComposerPath), true);

    $repositories = $composer['repositories'] ?? [];
    $paths = array_column($repositories, 'url');

    expect(in_array('packages/page-cache-entity', $paths, true))->toBeTrue();
});

it('declares marko/page-cache-entity as a self.version requirement in the root composer.json', function (): void {
    $rootComposerPath = dirname(__DIR__, 3) . '/composer.json';
    $composer = json_decode(file_get_contents($rootComposerPath), true);

    expect($composer['require'])->toHaveKey('marko/page-cache-entity')
        ->and($composer['require']['marko/page-cache-entity'])->toBe('self.version');
});

it('registers the package test autoload as Marko\\PageCache\\Entity\\Tests\\ in the root composer.json autoload-dev', function (): void {
    $rootComposerPath = dirname(__DIR__, 3) . '/composer.json';
    $composer = json_decode(file_get_contents($rootComposerPath), true);

    expect($composer['autoload-dev']['psr-4'])->toHaveKey('Marko\\PageCache\\Entity\\Tests\\')
        ->and($composer['autoload-dev']['psr-4']['Marko\\PageCache\\Entity\\Tests\\'])->toBe('packages/page-cache-entity/tests/');
});

it('lists page-cache-entity in the package dropdown of bug_report.yml and feature_request.yml', function (): void {
    $bugReportPath = dirname(__DIR__, 3) . '/.github/ISSUE_TEMPLATE/bug_report.yml';
    $featureRequestPath = dirname(__DIR__, 3) . '/.github/ISSUE_TEMPLATE/feature_request.yml';

    $bugReportContent = file_get_contents($bugReportPath);
    $featureRequestContent = file_get_contents($featureRequestPath);

    expect(str_contains($bugReportContent, '- page-cache-entity'))->toBeTrue()
        ->and(str_contains($featureRequestContent, '- page-cache-entity'))->toBeTrue();
});

it('has a README.md at packages/page-cache-entity/README.md', function (): void {
    $readmePath = dirname(__DIR__) . '/README.md';

    expect(file_exists($readmePath))->toBeTrue();
});

it('documents installation via composer require marko/page-cache-entity', function (): void {
    $readmePath = dirname(__DIR__) . '/README.md';
    $content = file_get_contents($readmePath);

    expect(str_contains($content, 'composer require marko/page-cache-entity'))->toBeTrue();
});

it('documents implementing IdentityInterface on an entity', function (): void {
    $readmePath = dirname(__DIR__) . '/README.md';
    $content = file_get_contents($readmePath);

    expect(str_contains($content, 'IdentityInterface'))->toBeTrue()
        ->and(str_contains($content, 'getIdentities'))->toBeTrue();
});

it('explains the three observer classes and the IdentityPurger service', function (): void {
    $readmePath = dirname(__DIR__) . '/README.md';
    $content = file_get_contents($readmePath);

    expect(str_contains($content, 'PurgeOnEntityCreated'))->toBeTrue()
        ->and(str_contains($content, 'PurgeOnEntityUpdated'))->toBeTrue()
        ->and(str_contains($content, 'PurgeOnEntityDeleted'))->toBeTrue()
        ->and(str_contains($content, 'IdentityPurger'))->toBeTrue();
});

it('lists the IdentityPurger signatures in the API Reference', function (): void {
    $readmePath = dirname(__DIR__) . '/README.md';
    $content = file_get_contents($readmePath);

    expect(str_contains($content, 'IdentityPurger::__construct(PageCacheInterface $pageCache)'))->toBeTrue()
        ->and(str_contains($content, 'IdentityPurger::purge(Entity $entity): void'))->toBeTrue();
});
