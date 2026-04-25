<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Core\Path\ProjectPaths;
use Marko\Vite\Exceptions\ViteConfigurationException;
use Marko\Vite\Vite;

beforeEach(function () {
    $this->basePath = dirname(__DIR__);
    $this->paths = new ProjectPaths($this->basePath);
});

function makeProjectPathsWithManifest(array|string $manifest): ProjectPaths
{
    $basePath = sys_get_temp_dir().'/marko-vite-test-'.bin2hex(random_bytes(6));
    $manifestDirectory = $basePath.'/public/build/.vite';

    mkdir($manifestDirectory, 0777, true);
    file_put_contents(
        $manifestDirectory.'/manifest.json',
        is_array($manifest) ? json_encode($manifest, JSON_THROW_ON_ERROR) : $manifest,
    );

    return new ProjectPaths($basePath);
}

test('vite returns manifest not found when manifest is missing', function () {
    $config = new ConfigRepository([
        'vite' => [
            'entry' => 'app/web/resources/js/app.js',
            'buildDirectory' => 'build',
            'manifestFilename' => '.vite/nonexistent.json',
            'useDevServer' => false,
        ],
    ]);

    $vite = new Vite($config, $this->paths);
    $tags = $vite->headTags();

    expect($tags)->toContain('Vite manifest not found');
});

test('vite detects dev server from config', function () {
    $config = new ConfigRepository([
        'vite' => [
            'useDevServer' => true,
        ],
    ]);

    $vite = new Vite($config, $this->paths);

    expect($vite->useDevServer())->toBeTrue();
});

test('vite uses the configured default entry when no entry is provided', function () {
    $config = new ConfigRepository([
        'vite' => [
            'entry' => 'app/web/resources/js/app.js',
            'buildDirectory' => 'build',
            'manifestFilename' => '.vite/nonexistent.json',
            'useDevServer' => false,
        ],
    ]);

    $vite = new Vite($config, $this->paths);
    $tags = $vite->headTags();

    expect($tags)->toContain('Vite manifest not found');
});

test('vite throws when no default entry is configured', function () {
    $config = new ConfigRepository([
        'vite' => [
            'entry' => '',
            'useDevServer' => false,
        ],
    ]);

    $vite = new Vite($config, $this->paths);

    expect(fn () => $vite->headTags())
        ->toThrow(ViteConfigurationException::class, 'vite.entry');
});

test('vite throws when dev server config is missing', function () {
    $config = new ConfigRepository([
        'vite' => [],
    ]);

    $vite = new Vite($config, $this->paths);

    expect(fn () => $vite->useDevServer())
        ->toThrow(ViteConfigurationException::class, 'vite.useDevServer');
});

test('vite dev server tags include vite client and entry', function () {
    $config = new ConfigRepository([
        'vite' => [
            'devServerUrl' => 'http://localhost:5173',
            'devServerStylesheets' => [
                'app/web/resources/css/app.css',
            ],
            'useDevServer' => true,
        ],
    ]);

    $vite = new Vite($config, $this->paths);
    $tags = $vite->headTags('app/web/resources/js/app.js');

    expect($tags)->toContain('@vite/client');
    expect($tags)->toContain('app/web/resources/js/app.js');
    expect($tags)->toContain('rel="stylesheet"');
    expect($tags)->toContain('app/web/resources/css/app.css');
    expect($tags)->not->toContain('@react-refresh');
});

test('vite throws when the dev server url is missing in dev mode', function () {
    $config = new ConfigRepository([
        'vite' => [
            'devServerUrl' => '',
            'useDevServer' => true,
            'devServerStylesheets' => [],
        ],
    ]);

    $vite = new Vite($config, $this->paths);

    expect(fn () => $vite->headTags('app/web/resources/js/app.js'))
        ->toThrow(ViteConfigurationException::class, 'vite.devServerUrl');
});

test('vite dev server tags use the configured dev server url', function () {
    $config = new ConfigRepository([
        'vite' => [
            'devServerUrl' => 'http://localhost:5174',
            'devServerStylesheets' => [],
            'useDevServer' => true,
        ],
    ]);

    $vite = new Vite($config, $this->paths);
    $tags = $vite->headTags('app/react-web/resources/js/app.jsx');

    expect($tags)->toContain('http://localhost:5174/@vite/client');
    expect($tags)->toContain('http://localhost:5174/app/react-web/resources/js/app.jsx');
    expect($tags)->toContain('http://localhost:5174/@react-refresh');
    expect($tags)->toContain('window.$RefreshReg$');
    expect($tags)->not->toContain('localhost:5173');
});

test('vite dev server tags skip react refresh preamble for svelte entries', function () {
    $config = new ConfigRepository([
        'vite' => [
            'devServerUrl' => 'http://localhost:5173',
            'devServerStylesheets' => [],
            'useDevServer' => true,
        ],
    ]);

    $vite = new Vite($config, $this->paths);
    $tags = $vite->headTags('app/svelte-web/resources/js/app.js');

    expect($tags)->toContain('app/svelte-web/resources/js/app.js');
    expect($tags)->not->toContain('@react-refresh');
    expect($tags)->not->toContain('window.$RefreshReg$');
});

test('vite returns css and js tags from a production manifest', function () {
    $config = new ConfigRepository([
        'vite' => [
            'entry' => 'app/web/resources/js/app.js',
            'buildDirectory' => 'build',
            'manifestFilename' => '.vite/manifest.json',
            'useDevServer' => false,
        ],
    ]);

    $paths = makeProjectPathsWithManifest([
        'app/web/resources/js/app.js' => [
            'file' => 'assets/app.123.js',
            'css' => ['assets/app.456.css'],
        ],
    ]);

    $tags = (new Vite($config, $paths))->headTags();

    expect($tags)->toContain('<link rel="stylesheet" href="/build/assets/app.456.css">');
    expect($tags)->toContain('<script type="module" src="/build/assets/app.123.js"></script>');
});

test('vite includes imported chunk css and modulepreload tags from a production manifest', function () {
    $config = new ConfigRepository([
        'vite' => [
            'entry' => 'app/web/resources/js/app.js',
            'buildDirectory' => 'build',
            'manifestFilename' => '.vite/manifest.json',
            'useDevServer' => false,
        ],
    ]);

    $paths = makeProjectPathsWithManifest([
        'app/web/resources/js/app.js' => [
            'file' => 'assets/app.123.js',
            'css' => ['assets/app.456.css'],
            'imports' => ['_shared.js', '_nested.js'],
        ],
        '_shared.js' => [
            'file' => 'assets/shared.789.js',
            'css' => ['assets/shared.789.css'],
            'imports' => ['_nested.js'],
        ],
        '_nested.js' => [
            'file' => 'assets/nested.999.js',
            'css' => ['assets/nested.999.css'],
        ],
    ]);

    $tags = (new Vite($config, $paths))->headTags();

    expect($tags)->toContain('<link rel="stylesheet" href="/build/assets/app.456.css">');
    expect($tags)->toContain('<link rel="stylesheet" href="/build/assets/shared.789.css">');
    expect($tags)->toContain('<link rel="stylesheet" href="/build/assets/nested.999.css">');
    expect($tags)->toContain('<script type="module" src="/build/assets/app.123.js"></script>');
    expect($tags)->toContain('<link rel="modulepreload" href="/build/assets/shared.789.js">');
    expect($tags)->toContain('<link rel="modulepreload" href="/build/assets/nested.999.js">');
    expect(substr_count($tags, 'assets/nested.999.css'))->toBe(1);
});

test('vite reports invalid manifests and missing entries', function () {
    $config = new ConfigRepository([
        'vite' => [
            'entry' => 'app/web/resources/js/app.js',
            'buildDirectory' => 'build',
            'manifestFilename' => '.vite/manifest.json',
            'useDevServer' => false,
        ],
    ]);

    $invalidManifestTags = (new Vite($config, makeProjectPathsWithManifest('not-json')))
        ->headTags();

    $missingEntryTags = (new Vite($config, makeProjectPathsWithManifest([])))
        ->headTags();

    expect($invalidManifestTags)->toContain('Vite manifest is invalid');
    expect($missingEntryTags)->toContain("Vite entry 'app/web/resources/js/app.js' not found");
});
