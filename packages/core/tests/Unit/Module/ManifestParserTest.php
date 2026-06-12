<?php

declare(strict_types=1);

use Marko\Core\Module\ManifestParser;
use Marko\Core\Module\ModuleManifest;

/**
 * Creates a temp module directory with composer.json and returns the path.
 *
 * @param array<string, string> $require
 */
function createTempModuleDir(array $require): string
{
    $tmpDir = sys_get_temp_dir() . '/marko-test-manifest-' . uniqid();
    mkdir($tmpDir, 0755, true);
    file_put_contents($tmpDir . '/composer.json', json_encode([
        'name' => 'vendor/test-module',
        'require' => $require,
    ]));

    return $tmpDir;
}

function removeTempModuleDir(string $tmpDir): void
{
    array_map('unlink', glob($tmpDir . '/*'));
    rmdir($tmpDir);
}

function parseModuleRequire(array $require): ModuleManifest
{
    $tmpDir = createTempModuleDir($require);
    $manifest = (new ManifestParser())->parse($tmpDir);
    removeTempModuleDir($tmpDir);

    return $manifest;
}

describe('ManifestParser', function (): void {
    it('does not drop a phpunit/phpunit requirement', function (): void {
        $manifest = parseModuleRequire(['phpunit/phpunit' => '^11.0']);

        expect($manifest->require)->toHaveKey('phpunit/phpunit');
    });

    it('does not drop a phpstan/phpstan requirement', function (): void {
        $manifest = parseModuleRequire(['phpstan/phpstan' => '^2.0']);

        expect($manifest->require)->toHaveKey('phpstan/phpstan');
    });

    it('drops the exact php platform requirement', function (): void {
        $manifest = parseModuleRequire(['php' => '>=8.5']);

        expect($manifest->require)->not->toHaveKey('php');
    });

    it('drops the php-64bit platform requirement', function (): void {
        $manifest = parseModuleRequire(['php-64bit' => '>=8.5']);

        expect($manifest->require)->not->toHaveKey('php-64bit');
    });

    it('drops ext-* requirements', function (): void {
        $manifest = parseModuleRequire(['ext-json' => '*', 'ext-mbstring' => '*']);

        expect($manifest->require)->not->toHaveKey('ext-json')
            ->and($manifest->require)->not->toHaveKey('ext-mbstring');
    });

    it('drops lib-* requirements', function (): void {
        $manifest = parseModuleRequire(['lib-curl' => '*', 'lib-openssl' => '*']);

        expect($manifest->require)->not->toHaveKey('lib-curl')
            ->and($manifest->require)->not->toHaveKey('lib-openssl');
    });

    it('keeps marko/* module requirements', function (): void {
        $manifest = parseModuleRequire([
            'php' => '>=8.5',
            'ext-json' => '*',
            'lib-curl' => '*',
            'marko/core' => '^1.0',
            'marko/routing' => '^1.0',
            'psr/log' => '^3.0',
        ]);

        expect($manifest->require)->toHaveKey('marko/core')
            ->and($manifest->require)->toHaveKey('marko/routing')
            ->and($manifest->require)->toHaveKey('psr/log')
            ->and($manifest->require)->not->toHaveKey('php')
            ->and($manifest->require)->not->toHaveKey('ext-json')
            ->and($manifest->require)->not->toHaveKey('lib-curl');
    });
});
