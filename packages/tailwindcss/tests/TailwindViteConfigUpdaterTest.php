<?php

declare(strict_types=1);

use Marko\Core\Path\ProjectPaths;
use Marko\TailwindCss\DefaultTailwindEntrypointProvider;
use Marko\TailwindCss\TailwindViteConfigUpdater;
use Marko\Vite\ScaffoldTemplateRenderer;
use Marko\Vite\ProjectFilePublisher;
use Marko\Vite\ValueObjects\ViteConfig;

beforeEach(function (): void {
    $this->tempDirectory = sys_get_temp_dir() . '/marko-tailwind-vite-config-' . bin2hex(random_bytes(6));
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

function makeTailwindViteConfigUpdater(
    string $directory,
    ?ViteConfig $viteConfig = null,
    string $cssEntrypoint = 'resources/css/app.css',
): TailwindViteConfigUpdater
{
    $viteConfig ??= new ViteConfig(
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

    return new TailwindViteConfigUpdater(
        $viteConfig,
        new ProjectPaths($directory),
        new ProjectFilePublisher(new ProjectPaths($directory)),
        new DefaultTailwindEntrypointProvider(
            new Marko\Config\ConfigRepository([
                'tailwindcss' => [
                    'enabled' => true,
                    'entrypoints' => [
                        'css' => $cssEntrypoint,
                    ],
                ],
            ]),
        ),
        new ScaffoldTemplateRenderer($viteConfig),
    );
}

test('tailwind vite config updater creates a tailwind-aware root vite config when missing', function (): void {
    $updater = makeTailwindViteConfigUpdater($this->tempDirectory);

    $result = $updater->ensureTailwindConfig();

    expect($result->status)->toBe('created');
    expect((string) file_get_contents($this->tempDirectory . '/vite.config.ts'))
        ->toContain("import tailwindcss from '@tailwindcss/vite';")
        ->toContain("entrypoints: ['resources/js/app.ts', 'resources/css/app.css']");
})->group('tailwindcss');

test('tailwind vite config updater honors custom configured paths', function (): void {
    $viteConfig = new ViteConfig(
        devServerUrl: 'http://localhost:5173',
        devProcessFilePath: $this->tempDirectory . '/.marko/dev.json',
        hotFilePath: $this->tempDirectory . '/public/hot',
        manifestPath: $this->tempDirectory . '/public/build/manifest.json',
        buildDirectory: '/build',
        assetsBaseUrl: '',
        defaultEntrypoints: [],
        rootEntrypointPath: 'frontend/main.ts',
        rootViteConfigPath: 'config/vite.app.ts',
    );

    $updater = makeTailwindViteConfigUpdater($this->tempDirectory, $viteConfig, 'frontend/styles/app.css');
    $result = $updater->ensureTailwindConfig();

    expect($result->status)->toBe('created');
    expect((string) file_get_contents($this->tempDirectory . '/config/vite.app.ts'))
        ->toContain("from '../vendor/marko/vite/resources/config/createViteConfig';")
        ->toContain("entrypoints: ['frontend/main.ts', 'frontend/styles/app.css']");
})->group('tailwindcss');

test('tailwind vite config updater derives the tailwind config from the vite stub', function (): void {
    $viteStub = (string) file_get_contents(dirname(__DIR__, 2) . '/vite/stubs/vite.config.ts');

    $updater = makeTailwindViteConfigUpdater($this->tempDirectory);
    $updater->ensureTailwindConfig();

    $tailwindConfig = (string) file_get_contents($this->tempDirectory . '/vite.config.ts');

    expect($tailwindConfig)
        ->toContain("import tailwindcss from '@tailwindcss/vite';")
        ->toContain("plugins: [tailwindcss()]")
        ->toContain("entrypoints: ['resources/js/app.ts', 'resources/css/app.css']");

    expect($tailwindConfig)
        ->not->toBe($viteStub);
})->group('tailwindcss');

test('tailwind vite config updater upgrades the published vite root config', function (): void {
    file_put_contents(
        $this->tempDirectory . '/vite.config.ts',
        (string) file_get_contents(dirname(__DIR__, 2) . '/vite/stubs/vite.config.ts'),
    );

    $updater = makeTailwindViteConfigUpdater($this->tempDirectory);
    $result = $updater->ensureTailwindConfig();

    expect($result->status)->toBe('replaced');
    expect((string) file_get_contents($this->tempDirectory . '/vite.config.ts'))
        ->toContain("import tailwindcss from '@tailwindcss/vite';")
        ->toContain("entrypoints: ['resources/js/app.ts', 'resources/css/app.css']");
})->group('tailwindcss');

test('tailwind vite config updater skips custom root vite config files unless forced', function (): void {
    file_put_contents(
        $this->tempDirectory . '/vite.config.ts',
        "import { defineConfig } from 'vite';\nexport default defineConfig({});\n",
    );

    $updater = makeTailwindViteConfigUpdater($this->tempDirectory);
    $result = $updater->ensureTailwindConfig();

    expect($result->status)->toBe('skipped');
    expect((string) file_get_contents($this->tempDirectory . '/vite.config.ts'))
        ->toContain("defineConfig({})");
})->group('tailwindcss');

test('tailwind vite config updater upgrades legacy tailwind proxy configs', function (): void {
    file_put_contents(
        $this->tempDirectory . '/vite.config.ts',
        "export { default } from './vendor/marko/tailwindcss/resources/config/vite.config.ts';\n",
    );

    $updater = makeTailwindViteConfigUpdater($this->tempDirectory);
    $result = $updater->ensureTailwindConfig();

    expect($result->status)->toBe('replaced');
    expect((string) file_get_contents($this->tempDirectory . '/vite.config.ts'))
        ->toContain("import tailwindcss from '@tailwindcss/vite';")
        ->toContain("entrypoints: ['resources/js/app.ts', 'resources/css/app.css']");
})->group('tailwindcss');

test('tailwind vite config updater upgrades inertia react configs without losing the framework plugin', function (): void {
    file_put_contents(
        $this->tempDirectory . '/vite.config.ts',
        "import { defineConfig } from 'vite';\n"
        . "import react from '@vitejs/plugin-react';\n"
        . "import { createBaseConfig } from './vendor/marko/vite/resources/config/createViteConfig';\n\n"
        . "export default defineConfig(\n"
        . "  createBaseConfig({\n"
        . "    plugins: [react()],\n"
        . "    entrypoints: ['resources/js/app.ts'],\n"
        . "  }),\n"
        . ");\n",
    );

    $result = makeTailwindViteConfigUpdater($this->tempDirectory)->ensureTailwindConfig();
    $tailwindConfig = (string) file_get_contents($this->tempDirectory . '/vite.config.ts');

    expect($result->status)->toBe('replaced')
        ->and($tailwindConfig)->toContain("import react from '@vitejs/plugin-react';")
        ->and($tailwindConfig)->toContain("import tailwindcss from '@tailwindcss/vite';")
        ->and($tailwindConfig)->toContain('plugins: [react(), tailwindcss()]')
        ->and($tailwindConfig)->toContain("entrypoints: ['resources/js/app.ts', 'resources/css/app.css']");
})->group('tailwindcss');

test('tailwind vite config updater preserves existing non-tailwind entrypoints when upgrading inertia configs', function (): void {
    file_put_contents(
        $this->tempDirectory . '/vite.config.ts',
        "import { defineConfig } from 'vite';\n"
        . "import react from '@vitejs/plugin-react';\n"
        . "import { createBaseConfig } from './vendor/marko/vite/resources/config/createViteConfig';\n\n"
        . "export default defineConfig(\n"
        . "  createBaseConfig({\n"
        . "    plugins: [react()],\n"
        . "    entrypoints: ['resources/js/app.ts', 'frontend/admin.ts'],\n"
        . "  }),\n"
        . ");\n",
    );

    $result = makeTailwindViteConfigUpdater($this->tempDirectory)->ensureTailwindConfig();
    $tailwindConfig = (string) file_get_contents($this->tempDirectory . '/vite.config.ts');

    expect($result->status)->toBe('replaced')
        ->and($tailwindConfig)->toContain("entrypoints: ['resources/js/app.ts', 'frontend/admin.ts', 'resources/css/app.css']");
})->group('tailwindcss');
