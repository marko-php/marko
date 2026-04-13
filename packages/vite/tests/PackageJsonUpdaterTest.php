<?php

declare(strict_types=1);

use Marko\Core\Path\ProjectPaths;
use Marko\Vite\PackageJsonUpdater;

beforeEach(function (): void {
    $this->tempDirectory = sys_get_temp_dir() . '/marko-vite-package-json-' . bin2hex(random_bytes(6));
    mkdir($this->tempDirectory, 0777, true);
});

afterEach(function (): void {
    if (!isset($this->tempDirectory) || !is_dir($this->tempDirectory)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($this->tempDirectory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
            continue;
        }

        unlink($item->getPathname());
    }

    rmdir($this->tempDirectory);
});

test('package json updater creates a minimal package json with requested vite setup', function (): void {
    $updater = new PackageJsonUpdater(new ProjectPaths($this->tempDirectory));

    $result = $updater->update(
        fields: [
            'private' => true,
            'type' => 'module',
        ],
        scripts: [
            'dev' => 'vite --config ./vite.config.ts',
            'build' => 'vite build --config ./vite.config.ts',
        ],
        devDependencies: [
            'vite' => 'latest',
        ],
    );

    expect($result->createdFile)->toBeTrue();
    expect($result->added)->toBe([
        'field `type`',
        'script `dev`',
        'script `build`',
        'devDependency `vite`',
    ]);

    $packageJson = json_decode(
        (string) file_get_contents($this->tempDirectory . '/package.json'),
        true,
        flags: JSON_THROW_ON_ERROR
    );

    expect($packageJson)->toMatchArray([
        'private' => true,
        'type' => 'module',
        'scripts' => [
            'dev' => 'vite --config ./vite.config.ts',
            'build' => 'vite build --config ./vite.config.ts',
        ],
        'devDependencies' => [
            'vite' => 'latest',
        ],
    ]);
})->group('vite');

test('package json updater skips conflicting values unless forced', function (): void {
    file_put_contents($this->tempDirectory . '/package.json', json_encode([
        'private' => true,
        'type' => 'commonjs',
        'scripts' => [
            'dev' => 'custom-dev',
        ],
        'devDependencies' => [
            'vite' => '^5.0.0',
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $updater = new PackageJsonUpdater(new ProjectPaths($this->tempDirectory));

    $result = $updater->update(
        fields: [
            'type' => 'module',
        ],
        scripts: [
            'dev' => 'vite --config ./vite.config.ts',
            'build' => 'vite build --config ./vite.config.ts',
        ],
        devDependencies: [
            'vite' => 'latest',
        ],
    );

    expect($result->added)->toBe([
        'script `build`',
    ]);
    expect($result->skipped)->toBe([
        'field `type` because it already exists with a different value',
        'script `dev` because it already exists with a different value',
        'devDependency `vite` because it already exists with a different value',
    ]);

    $packageJson = json_decode(
        (string) file_get_contents($this->tempDirectory . '/package.json'),
        true,
        flags: JSON_THROW_ON_ERROR
    );

    expect($packageJson['type'])->toBe('commonjs');
    expect($packageJson['scripts']['dev'])->toBe('custom-dev');
    expect($packageJson['scripts']['build'])->toBe('vite build --config ./vite.config.ts');
    expect($packageJson['devDependencies']['vite'])->toBe('^5.0.0');
})->group('vite');

test('package json updater supports dry run without writing files', function (): void {
    $updater = new PackageJsonUpdater(new ProjectPaths($this->tempDirectory));

    $result = $updater->update(
        fields: [
            'private' => true,
        ],
        scripts: [
            'dev' => 'vite --config ./vite.config.ts',
        ],
        devDependencies: [
            'vite' => 'latest',
        ],
        dryRun: true,
    );

    expect($result->createdFile)->toBeTrue();
    expect(file_exists($this->tempDirectory . '/package.json'))->toBeFalse();
})->group('vite');
