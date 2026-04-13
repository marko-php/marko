<?php

declare(strict_types=1);

use Marko\Core\Path\ProjectPaths;
use Marko\Vite\Events\EntrypointsResolved;
use Marko\Vite\ProjectFilePublisher;
use Marko\Vite\ScaffoldTemplateRenderer;
use Marko\Vite\ScaffoldViteConfigUpdater;
use Marko\Vite\ValueObjects\DevServer;
use Marko\Vite\ValueObjects\Manifest;
use Marko\Vite\ValueObjects\ManifestEntry;
use Marko\Vite\ValueObjects\PackageJsonUpdateResult;
use Marko\Vite\ValueObjects\ResolvedEntrypointCollection;
use Marko\Vite\ValueObjects\ViteConfig;

beforeEach(function (): void {
    $this->tempDirectory = sys_get_temp_dir() . '/marko-vite-values-' . bin2hex(random_bytes(6));
    mkdir($this->tempDirectory, 0777, true);
});

afterEach(function (): void {
    if (! isset($this->tempDirectory) || ! is_dir($this->tempDirectory)) {
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

function viteValueConfig(string $directory): ViteConfig
{
    return new ViteConfig(
        devServerUrl: 'http://localhost:5173',
        devProcessFilePath: $directory . '/.marko/dev.json',
        hotFilePath: $directory . '/public/hot',
        manifestPath: $directory . '/public/build/manifest.json',
        buildDirectory: '/build',
        assetsBaseUrl: '',
        defaultEntrypoints: [],
        rootEntrypointPath: 'resources/js/app.ts',
        rootViteConfigPath: 'vite.config.ts',
    );
}

test('dev server joins asset urls without duplicate slashes', function (): void {
    $server = new DevServer('http://localhost:5173/');

    expect($server->assetUrl('/resources/js/app.ts'))
        ->toBe('http://localhost:5173/resources/js/app.ts');
})->group('vite');

test('manifest returns entries and throws for missing entrypoints', function (): void {
    $entry = new ManifestEntry(
        name: 'app',
        file: 'assets/app.js',
        source: 'resources/js/app.ts',
        isEntry: true,
        css: ['assets/app.css'],
        imports: ['assets/chunk.js'],
    );
    $manifest = new Manifest('/tmp/manifest.json', [
        'resources/js/app.ts' => $entry,
    ]);

    expect($manifest->entry('resources/js/app.ts'))->toBe($entry)
        ->and($entry->isCss())->toBeFalse()
        ->and((new ManifestEntry('style', 'assets/app.css'))->isCss())->toBeTrue()
        ->and(fn () => $manifest->entry('resources/js/missing.ts'))
        ->toThrow(Marko\Vite\Exceptions\EntrypointNotFoundException::class);
})->group('vite');

test('package json update result reports whether anything changed', function (): void {
    expect((new PackageJsonUpdateResult())->changed())->toBeFalse()
        ->and((new PackageJsonUpdateResult(createdFile: true))->changed())->toBeTrue()
        ->and((new PackageJsonUpdateResult(added: ['vite']))->changed())->toBeTrue()
        ->and((new PackageJsonUpdateResult(updated: ['react']))->changed())->toBeTrue();
})->group('vite');

test('entrypoints resolved event keeps requested entrypoints assets and dev mode', function (): void {
    $assets = new ResolvedEntrypointCollection(
        entrypoints: ['resources/js/app.ts'],
        preloads: [],
        styles: [],
        scripts: [],
        development: true,
    );
    $event = new EntrypointsResolved(['resources/js/app.ts'], $assets, true);

    expect($event->requestedEntrypoints)->toBe(['resources/js/app.ts'])
        ->and($event->assets)->toBe($assets)
        ->and($event->development)->toBeTrue();
})->group('vite');

test('scaffold vite config updater handles missing replaceable and custom configs', function (): void {
    $config = viteValueConfig($this->tempDirectory);
    $updater = new class (
        $config,
        new ProjectPaths($this->tempDirectory),
        new ProjectFilePublisher(new ProjectPaths($this->tempDirectory)),
        new ScaffoldTemplateRenderer($config),
    ) extends ScaffoldViteConfigUpdater {
        public function ensure(
            string $missing,
            string $present,
            array $needles = [],
            bool $force = false,
            bool $dryRun = false,
        ): Marko\Vite\ValueObjects\FilePublishResult {
            return $this->ensureConfig($missing, $present, $needles, $force, $dryRun);
        }

        public function entrypoints(string $contents): array
        {
            return $this->entrypointsForExistingConfig($contents);
        }

        public function replaceable(string $contents): bool
        {
            return $this->isReplaceableViteStub($contents);
        }

        public function tailwind(string $contents): bool
        {
            return $this->containsTailwindPlugin($contents);
        }
    };

    $created = $updater->ensure('export default {};', 'export default {};');
    file_put_contents($this->tempDirectory . '/vite.config.ts', "import tailwind from '@tailwindcss/vite';\n");
    $alreadyPresent = $updater->ensure('ignored', 'ignored', ['@tailwindcss/vite']);

    file_put_contents($this->tempDirectory . '/vite.config.ts', "export { default } from './vendor/marko/vite/resources/config/vite.config.ts';\n");
    $replaced = $updater->ensure('ignored', "export default { plugins: ['tailwind'] };\n");

    file_put_contents($this->tempDirectory . '/vite.config.ts', "import foo from 'foo';\n");
    $skipped = $updater->ensure('ignored', "export default { plugins: ['tailwind'] };\n");

    expect($created->status)->toBe('created')
        ->and($updater->tailwind("import tailwind from '@tailwindcss/vite';"))->toBeTrue()
        ->and($updater->entrypoints("createBaseConfig({\n  entrypoints: ['resources/js/app.ts', 'resources/js/admin.ts', 'resources/js/admin.ts'],\n});"))->toBe([
            'resources/js/app.ts',
            'resources/js/admin.ts',
        ])
        ->and($alreadyPresent->status)->toBe('already_present')
        ->and($updater->replaceable("export { default } from './vendor/marko/vite/resources/config/vite.config.ts';\n"))->toBeTrue()
        ->and($replaced->status)->toBe('replaced')
        ->and($skipped->status)->toBe('skipped');
})->group('vite');
