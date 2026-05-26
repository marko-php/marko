<?php

declare(strict_types=1);

it('has a valid composer.json with correct name and namespace', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';

    expect(file_exists($composerPath))->toBeTrue();

    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer)->toBeArray()
        ->and($composer['name'])->toBe('marko/database-readwrite')
        ->and($composer['type'])->toBe('library')
        ->and($composer['license'])->toBe('MIT')
        ->and($composer['autoload']['psr-4'])->toHaveKey('Marko\\Database\\ReadWrite\\')
        ->and($composer['autoload']['psr-4']['Marko\\Database\\ReadWrite\\'])->toBe('src/')
        ->and($composer['autoload-dev']['psr-4'])->toHaveKey('Marko\\Database\\ReadWrite\\Tests\\')
        ->and($composer['autoload-dev']['psr-4']['Marko\\Database\\ReadWrite\\Tests\\'])->toBe('tests/');
});

it('declares marko/core and marko/database as required dependencies', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['require'])->toHaveKey('marko/core')
        ->and($composer['require']['marko/core'])->toBe('self.version')
        ->and($composer['require'])->toHaveKey('marko/database')
        ->and($composer['require']['marko/database'])->toBe('self.version')
        ->and($composer['require'])->toHaveKey('php')
        ->and($composer['require']['php'])->toBe('^8.5')
        ->and($composer['require'])->toHaveKey('ext-pdo')
        ->and($composer['require']['ext-pdo'])->toBe('*');
});

it('does not hardcode a version key in composer.json', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer)->not->toHaveKey('version');
});

it('does not require any specific database driver package', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    $require = $composer['require'] ?? [];

    expect(array_key_exists('marko/database-pgsql', $require))->toBeFalse()
        ->and(array_key_exists('marko/database-mysql', $require))->toBeFalse();
});

it('has a module.php that loads without error', function (): void {
    $modulePath = dirname(__DIR__) . '/module.php';

    expect(file_exists($modulePath))->toBeTrue();

    $module = require $modulePath;

    expect($module)->toBeArray();
});

it('appears in the root composer.json path repositories list', function (): void {
    $rootComposerPath = dirname(__DIR__, 3) . '/composer.json';
    $rootComposer = json_decode(file_get_contents($rootComposerPath), true);

    $repoUrls = array_column($rootComposer['repositories'], 'url');

    expect(in_array('packages/database-readwrite', $repoUrls, true))->toBeTrue();
});

it('appears in the root composer.json require section with self.version', function (): void {
    $rootComposerPath = dirname(__DIR__, 3) . '/composer.json';
    $rootComposer = json_decode(file_get_contents($rootComposerPath), true);

    expect($rootComposer['require'])->toHaveKey('marko/database-readwrite')
        ->and($rootComposer['require']['marko/database-readwrite'])->toBe('self.version');
});

it('appears in RootComposerJsonTest expected-package list', function (): void {
    $testFilePath = dirname(__DIR__, 3) . '/packages/framework/tests/RootComposerJsonTest.php';

    expect(file_exists($testFilePath))->toBeTrue();

    $contents = file_get_contents($testFilePath);

    expect(str_contains($contents, "'marko/database-readwrite'"))->toBeTrue();
});

it(
    'is auto-discovered by PackagingTest via scandir (no list update needed — just a smoke check that the new directory satisfies the .gitattributes assertion)',
    function (): void {
        $gitattributesPath = dirname(__DIR__) . '/.gitattributes';

        expect(file_exists($gitattributesPath))->toBeTrue();

        $contents = file_get_contents($gitattributesPath);

        expect(str_contains($contents, 'export-ignore'))->toBeTrue();
    },
);

it(
    'appears in both .github/ISSUE_TEMPLATE/bug_report.yml and feature_request.yml package dropdowns',
    function (): void {
        $rootPath = dirname(__DIR__, 3);

        $bugReport = file_get_contents($rootPath . '/.github/ISSUE_TEMPLATE/bug_report.yml');
        $featureRequest = file_get_contents($rootPath . '/.github/ISSUE_TEMPLATE/feature_request.yml');

        expect(str_contains($bugReport, '- database-readwrite'))->toBeTrue()
            ->and(str_contains($featureRequest, '- database-readwrite'))->toBeTrue();
    },
);
