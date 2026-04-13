<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Marko\Core\Path\ProjectPaths;
use Marko\Inertia\Svelte\InertiaSvelteViteConfigUpdater;
use Marko\Vite\ProjectFilePublisher;
use Marko\Vite\ScaffoldTemplateRenderer;
use Marko\Vite\ValueObjects\ViteConfig;

beforeEach(function (): void {
    $this->tempDirectory = sys_get_temp_dir() . '/marko-inertia-svelte-vite-config-' . bin2hex(random_bytes(6));
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

function makeInertiaSvelteViteConfigUpdater(string $directory): InertiaSvelteViteConfigUpdater
{
    $viteConfig = new ViteConfig(
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

    return new InertiaSvelteViteConfigUpdater(
        $viteConfig,
        new ProjectPaths($directory),
        new ProjectFilePublisher(new ProjectPaths($directory)),
        new ScaffoldTemplateRenderer($viteConfig),
    );
}

test('inertia svelte vite config updater creates a svelte-aware root vite config when missing', function (): void {
    $updater = makeInertiaSvelteViteConfigUpdater($this->tempDirectory);

    $result = $updater->ensureSvelteConfig();

    expect($result->status)->toBe('created');
    expect((string) file_get_contents($this->tempDirectory . '/vite.config.ts'))
        ->toContain("import { svelte } from '@sveltejs/vite-plugin-svelte';")
        ->toContain('plugins: [svelte()]');
})->group('inertia-svelte');

test('inertia svelte vite config updater upgrades the default vite stub', function (): void {
    file_put_contents(
        $this->tempDirectory . '/vite.config.ts',
        (string) file_get_contents(dirname(__DIR__, 2) . '/vite/stubs/vite.config.ts'),
    );

    $updater = makeInertiaSvelteViteConfigUpdater($this->tempDirectory);
    $result = $updater->ensureSvelteConfig();

    expect($result->status)->toBe('replaced');
    expect((string) file_get_contents($this->tempDirectory . '/vite.config.ts'))
        ->toContain("import { svelte } from '@sveltejs/vite-plugin-svelte';")
        ->toContain('plugins: [svelte()]');
})->group('inertia-svelte');

test('inertia svelte vite config updater preserves tailwind support when upgrading a tailwind config', function (): void {
    file_put_contents(
        $this->tempDirectory . '/vite.config.ts',
        "import { defineConfig } from 'vite';\n"
        . "import tailwindcss from '@tailwindcss/vite';\n"
        . "import { createBaseConfig } from './vendor/marko/vite/resources/config/createViteConfig';\n\n"
        . "export default defineConfig(\n"
        . "  createBaseConfig({\n"
        . "    plugins: [tailwindcss()],\n"
        . "    entrypoints: ['resources/js/app.ts', 'resources/css/app.css'],\n"
        . "  }),\n"
        . ");\n",
    );

    $updater = makeInertiaSvelteViteConfigUpdater($this->tempDirectory);
    $result = $updater->ensureSvelteConfig();

    expect($result->status)->toBe('replaced');
    expect((string) file_get_contents($this->tempDirectory . '/vite.config.ts'))
        ->toContain("import { svelte } from '@sveltejs/vite-plugin-svelte';")
        ->toContain("import tailwindcss from '@tailwindcss/vite';")
        ->toContain('plugins: [svelte(), tailwindcss()]')
        ->toContain("entrypoints: ['resources/js/app.ts', 'resources/css/app.css']");
})->group('inertia-svelte');

test('inertia svelte vite config updater preserves custom tailwind entrypoints', function (): void {
    file_put_contents(
        $this->tempDirectory . '/vite.config.ts',
        "import { defineConfig } from 'vite';\n"
        . "import tailwindcss from '@tailwindcss/vite';\n"
        . "import { createBaseConfig } from './vendor/marko/vite/resources/config/createViteConfig';\n\n"
        . "export default defineConfig(\n"
        . "  createBaseConfig({\n"
        . "    plugins: [tailwindcss()],\n"
        . "    entrypoints: ['resources/js/app.ts', 'frontend/styles/site.css'],\n"
        . "  }),\n"
        . ");\n",
    );

    $result = makeInertiaSvelteViteConfigUpdater($this->tempDirectory)->ensureSvelteConfig();

    expect($result->status)->toBe('replaced');
    expect((string) file_get_contents($this->tempDirectory . '/vite.config.ts'))
        ->toContain("entrypoints: ['resources/js/app.ts', 'frontend/styles/site.css']");
})->group('inertia-svelte');
