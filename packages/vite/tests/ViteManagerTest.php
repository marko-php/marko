<?php

declare(strict_types=1);

use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Core\Path\ProjectPaths;
use Marko\Vite\AssetUrlGenerator;
use Marko\Vite\Contracts\AssetUrlGeneratorInterface;
use Marko\Vite\Contracts\DefaultEntrypointProviderInterface;
use Marko\Vite\Contracts\DevServerResolverInterface;
use Marko\Vite\Contracts\EntrypointResolverInterface;
use Marko\Vite\Contracts\ManifestRepositoryInterface;
use Marko\Vite\Contracts\TagRendererInterface;
use Marko\Vite\DefaultEntrypointProvider;
use Marko\Vite\Exceptions\EntrypointNotFoundException;
use Marko\Vite\Exceptions\ManifestNotFoundException;
use Marko\Vite\DevServerResolver;
use Marko\Vite\EntrypointResolver;
use Marko\Vite\ManifestRepository;
use Marko\Vite\TagRenderer;
use Marko\Vite\ValueObjects\ViteConfig;
use Marko\Vite\ViteManager;

beforeEach(function (): void {
    $this->tempDirectory = sys_get_temp_dir() . '/marko-vite-tests-' . bin2hex(random_bytes(6));
    mkdir($this->tempDirectory, 0777, true);
    mkdir($this->tempDirectory . '/public/build', 0777, true);
    $GLOBALS['__vite_test_project_root'] = $this->tempDirectory;
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
    unset($GLOBALS['__vite_test_project_root']);
});

function makeViteManager(ViteConfig $config): ViteManager
{
    $container = new Container();
    $events = new class () implements EventDispatcherInterface {
        public array $events = [];

        public function dispatch(Marko\Core\Event\Event $event): void
        {
            $this->events[] = $event;
        }
    };

    $container->instance(ContainerInterface::class, $container);
    $container->instance(EventDispatcherInterface::class, $events);
    $container->instance(ViteConfig::class, $config);
    $container->instance(ProjectPaths::class, new ProjectPaths($GLOBALS['__vite_test_project_root'] ?? getcwd()));
    $container->bind(ManifestRepositoryInterface::class, ManifestRepository::class);
    $container->bind(DevServerResolverInterface::class, DevServerResolver::class);
    $container->bind(AssetUrlGeneratorInterface::class, AssetUrlGenerator::class);
    $container->bind(DefaultEntrypointProviderInterface::class, DefaultEntrypointProvider::class);
    $container->bind(EntrypointResolverInterface::class, EntrypointResolver::class);
    $container->bind(TagRendererInterface::class, TagRenderer::class);

    return new ViteManager(
        $container->get(DevServerResolverInterface::class),
        $container->get(EntrypointResolverInterface::class),
        $container->get(TagRendererInterface::class),
    );
}

test('dev mode tag rendering', function (): void {
    file_put_contents($this->tempDirectory . '/public/hot', 'http://localhost:5173');

    $manager = makeViteManager(new ViteConfig(
        devServerUrl: 'http://127.0.0.1:65530',
        devProcessFilePath: $this->tempDirectory . '/.marko/dev.json',
        hotFilePath: $this->tempDirectory . '/public/hot',
        manifestPath: $this->tempDirectory . '/public/build/manifest.json',
        buildDirectory: '/build',
        assetsBaseUrl: '',
        defaultEntrypoints: [],
        rootEntrypointPath: 'resources/js/app.ts',
        rootViteConfigPath: 'vite.config.ts',
    ));

    $html = $manager->tags('resources/js/app.js');

    expect($html)->toContain('<script type="module" src="http://localhost:5173/@vite/client"></script>');
    expect($html)->toContain('<script type="module" src="http://localhost:5173/resources/js/app.js"></script>');
})->group('vite');

test('production manifest tag rendering', function (): void {
    file_put_contents($this->tempDirectory . '/public/build/manifest.json', json_encode([
        'resources/js/app.js' => [
            'file' => 'assets/app.123.js',
            'isEntry' => true,
        ],
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

    $manager = makeViteManager(new ViteConfig(
        devServerUrl: 'http://127.0.0.1:65530',
        devProcessFilePath: $this->tempDirectory . '/.marko/dev.json',
        hotFilePath: $this->tempDirectory . '/public/hot',
        manifestPath: $this->tempDirectory . '/public/build/manifest.json',
        buildDirectory: '/build',
        assetsBaseUrl: '',
        defaultEntrypoints: [],
        rootEntrypointPath: 'resources/js/app.ts',
        rootViteConfigPath: 'vite.config.ts',
    ));

    $html = $manager->tags('resources/js/app.js');

    expect($html)->toContain('<script type="module" src="/build/assets/app.123.js"></script>');
})->group('vite');

test('missing manifest failure', function (): void {
    $manager = makeViteManager(new ViteConfig(
        devServerUrl: 'http://127.0.0.1:65530',
        devProcessFilePath: $this->tempDirectory . '/.marko/dev.json',
        hotFilePath: $this->tempDirectory . '/public/hot',
        manifestPath: $this->tempDirectory . '/public/build/manifest.json',
        buildDirectory: '/build',
        assetsBaseUrl: '',
        defaultEntrypoints: [],
        rootEntrypointPath: 'resources/js/app.ts',
        rootViteConfigPath: 'vite.config.ts',
    ));

    $manager->tags('resources/js/app.js');
})->throws(ManifestNotFoundException::class)->group('vite');

test('missing entrypoint failure', function (): void {
    file_put_contents($this->tempDirectory . '/public/build/manifest.json', json_encode([
        'resources/js/other.js' => [
            'file' => 'assets/other.123.js',
            'isEntry' => true,
        ],
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

    $manager = makeViteManager(new ViteConfig(
        devServerUrl: 'http://127.0.0.1:65530',
        devProcessFilePath: $this->tempDirectory . '/.marko/dev.json',
        hotFilePath: $this->tempDirectory . '/public/hot',
        manifestPath: $this->tempDirectory . '/public/build/manifest.json',
        buildDirectory: '/build',
        assetsBaseUrl: '',
        defaultEntrypoints: [],
        rootEntrypointPath: 'resources/js/app.ts',
        rootViteConfigPath: 'vite.config.ts',
    ));

    $manager->tags('resources/js/app.js');
})->throws(EntrypointNotFoundException::class)->group('vite');

test('css emitted from js entrypoint manifest data', function (): void {
    file_put_contents($this->tempDirectory . '/public/build/manifest.json', json_encode([
        'resources/js/app.js' => [
            'file' => 'assets/app.123.js',
            'isEntry' => true,
            'css' => [
                'assets/app.456.css',
            ],
            'imports' => [
                '_shared.789.js',
            ],
        ],
        '_shared.789.js' => [
            'file' => 'assets/shared.789.js',
            'css' => [
                'assets/shared.789.css',
            ],
        ],
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

    $manager = makeViteManager(new ViteConfig(
        devServerUrl: 'http://127.0.0.1:65530',
        devProcessFilePath: $this->tempDirectory . '/.marko/dev.json',
        hotFilePath: $this->tempDirectory . '/public/hot',
        manifestPath: $this->tempDirectory . '/public/build/manifest.json',
        buildDirectory: '/build',
        assetsBaseUrl: '',
        defaultEntrypoints: [],
        rootEntrypointPath: 'resources/js/app.ts',
        rootViteConfigPath: 'vite.config.ts',
    ));

    $html = $manager->tags('resources/js/app.js');

    expect($html)->toContain('<link rel="modulepreload" href="/build/assets/shared.789.js">');
    expect($html)->toContain('<link rel="stylesheet" href="/build/assets/app.456.css">');
    expect($html)->toContain('<link rel="stylesheet" href="/build/assets/shared.789.css">');
    expect($html)->toContain('<script type="module" src="/build/assets/app.123.js"></script>');
})->group('vite');

test('default root entrypoint is used when no entrypoint is provided', function (): void {
    file_put_contents($this->tempDirectory . '/public/build/manifest.json', json_encode([
        'resources/js/app.ts' => [
            'file' => 'assets/app.123.js',
            'isEntry' => true,
        ],
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

    $manager = makeViteManager(new ViteConfig(
        devServerUrl: 'http://127.0.0.1:65530',
        devProcessFilePath: $this->tempDirectory . '/.marko/dev.json',
        hotFilePath: $this->tempDirectory . '/public/hot',
        manifestPath: $this->tempDirectory . '/public/build/manifest.json',
        buildDirectory: '/build',
        assetsBaseUrl: '',
        defaultEntrypoints: [],
        rootEntrypointPath: 'resources/js/app.ts',
        rootViteConfigPath: 'vite.config.ts',
    ));

    $html = $manager->tags();

    expect($html)->toContain('<script type="module" src="/build/assets/app.123.js"></script>');
})->group('vite');

test('configured default entrypoints override the root entrypoint', function (): void {
    file_put_contents($this->tempDirectory . '/public/build/manifest.json', json_encode([
        'resources/js/admin.ts' => [
            'file' => 'assets/admin.123.js',
            'isEntry' => true,
        ],
        'resources/js/app.ts' => [
            'file' => 'assets/app.123.js',
            'isEntry' => true,
        ],
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

    $manager = makeViteManager(new ViteConfig(
        devServerUrl: 'http://127.0.0.1:65530',
        devProcessFilePath: $this->tempDirectory . '/.marko/dev.json',
        hotFilePath: $this->tempDirectory . '/public/hot',
        manifestPath: $this->tempDirectory . '/public/build/manifest.json',
        buildDirectory: '/build',
        assetsBaseUrl: '',
        defaultEntrypoints: ['resources/js/admin.ts'],
        rootEntrypointPath: 'resources/js/app.ts',
        rootViteConfigPath: 'vite.config.ts',
    ));

    $html = $manager->tags();

    expect($html)->toContain('<script type="module" src="/build/assets/admin.123.js"></script>');
    expect($html)->not->toContain('/build/assets/app.123.js');
})->group('vite');

test('stale frontend process metadata does not force development mode', function (): void {
    mkdir($this->tempDirectory . '/.marko', 0777, true);
    file_put_contents($this->tempDirectory . '/.marko/dev.json', json_encode([
        'processes' => [
            [
                'name' => 'frontend',
                'pid' => 999999,
                'command' => 'npm run dev',
                'port' => 5173,
            ],
        ],
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

    file_put_contents($this->tempDirectory . '/public/build/manifest.json', json_encode([
        'resources/js/app.ts' => [
            'file' => 'assets/app.123.js',
            'isEntry' => true,
        ],
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

    $manager = makeViteManager(new ViteConfig(
        devServerUrl: 'http://127.0.0.1:65530',
        devProcessFilePath: $this->tempDirectory . '/.marko/dev.json',
        hotFilePath: $this->tempDirectory . '/public/hot',
        manifestPath: $this->tempDirectory . '/public/build/manifest.json',
        buildDirectory: '/build',
        assetsBaseUrl: '',
        defaultEntrypoints: [],
        rootEntrypointPath: 'resources/js/app.ts',
        rootViteConfigPath: 'vite.config.ts',
    ));

    $html = $manager->tags();

    expect($html)->toContain('<script type="module" src="/build/assets/app.123.js"></script>');
    expect($html)->not->toContain('http://localhost:5173/@vite/client');
})->group('vite');

test('reachable configured dev server enables development mode without marker files', function (): void {
    $server = stream_socket_server('tcp://127.0.0.1:0');

    expect($server)->not->toBeFalse();

    $address = stream_socket_get_name($server, false);

    expect($address)->toBeString();

    $port = (int) substr((string) $address, strrpos((string) $address, ':') + 1);

    $manager = makeViteManager(new ViteConfig(
        devServerUrl: "http://127.0.0.1:$port",
        devProcessFilePath: $this->tempDirectory . '/.marko/dev.json',
        hotFilePath: $this->tempDirectory . '/public/hot',
        manifestPath: $this->tempDirectory . '/public/build/manifest.json',
        buildDirectory: '/build',
        assetsBaseUrl: '',
        defaultEntrypoints: [],
        rootEntrypointPath: 'resources/js/app.ts',
        rootViteConfigPath: 'vite.config.ts',
    ));

    $html = $manager->tags('resources/js/app.js');

    fclose($server);

    expect($html)->toContain("<script type=\"module\" src=\"http://127.0.0.1:$port/@vite/client\"></script>")
        ->and($html)->toContain(
            "<script type=\"module\" src=\"http://127.0.0.1:$port/resources/js/app.js\"></script>"
        );
})->group('vite');
