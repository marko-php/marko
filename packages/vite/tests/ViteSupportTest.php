<?php

declare(strict_types=1);

use Marko\Core\Event\Event;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Core\Path\ProjectPaths;
use Marko\Vite\AssetUrlGenerator;
use Marko\Vite\DefaultEntrypointProvider;
use Marko\Vite\DevServerResolver;
use Marko\Vite\Exceptions\DevServerUnavailableException;
use Marko\Vite\Exceptions\ManifestNotFoundException;
use Marko\Vite\ManifestRepository;
use Marko\Vite\ProjectFilePublisher;
use Marko\Vite\TagRenderer;
use Marko\Vite\ValueObjects\AssetTag;
use Marko\Vite\ValueObjects\ManifestLoaded;
use Marko\Vite\ValueObjects\ResolvedAsset;
use Marko\Vite\ValueObjects\ResolvedEntrypointCollection;
use Marko\Vite\ValueObjects\ViteConfig;
use Marko\Vite\ViteViewHelper;

beforeEach(function (): void {
    $this->tempDirectory = sys_get_temp_dir() . '/marko-vite-support-' . bin2hex(random_bytes(6));
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

function viteSupportConfig(string $directory, array $overrides = []): ViteConfig
{
    return new ViteConfig(
        devServerUrl: $overrides['devServerUrl'] ?? 'http://localhost:5173',
        devProcessFilePath: $overrides['devProcessFilePath'] ?? $directory . '/.marko/dev.json',
        hotFilePath: $overrides['hotFilePath'] ?? $directory . '/public/hot',
        manifestPath: $overrides['manifestPath'] ?? $directory . '/public/build/manifest.json',
        buildDirectory: $overrides['buildDirectory'] ?? '/build',
        assetsBaseUrl: $overrides['assetsBaseUrl'] ?? '',
        defaultEntrypoints: $overrides['defaultEntrypoints'] ?? [],
        rootEntrypointPath: $overrides['rootEntrypointPath'] ?? 'resources/js/app.ts',
        rootViteConfigPath: $overrides['rootViteConfigPath'] ?? 'vite.config.ts',
    );
}

test('project file publisher reports create skip and replace states', function (): void {
    $publisher = new ProjectFilePublisher(new ProjectPaths($this->tempDirectory));

    $created = $publisher->publish('resources/js/app.ts', 'first');
    $skipped = $publisher->publish('resources/js/app.ts', 'second');
    $replaced = $publisher->publish('resources/js/app.ts', 'third', force: true);

    expect($created->status)->toBe('created')
        ->and($skipped->status)->toBe('skipped')
        ->and($replaced->status)->toBe('replaced')
        ->and((string) file_get_contents($this->tempDirectory . '/resources/js/app.ts'))->toBe('third');
})->group('vite');

test('project file publisher reports failed writes instead of pretending success', function (): void {
    file_put_contents($this->tempDirectory . '/resources', 'blocking file');

    $publisher = new ProjectFilePublisher(new ProjectPaths($this->tempDirectory));
    $result = $publisher->publish('resources/js/app.ts', 'blocked');

    expect($result->status)->toBe('failed')
        ->and($result->message)->toContain('Could not create directory');
})->group('vite');

test('manifest repository dispatches an event and ignores malformed entries', function (): void {
    mkdir($this->tempDirectory . '/public/build', 0777, true);
    file_put_contents($this->tempDirectory . '/public/build/manifest.json', json_encode([
        'resources/js/app.ts' => [
            'file' => 'assets/app.js',
            'isEntry' => true,
        ],
        'broken' => 'nope',
        'missing-file' => [
            'imports' => ['x'],
        ],
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

    $events = new class () implements EventDispatcherInterface {
        public array $events = [];

        public function dispatch(Event $event): void
        {
            $this->events[] = $event;
        }
    };

    $repository = new ManifestRepository(viteSupportConfig($this->tempDirectory), $events);
    $manifest = $repository->manifest();

    expect(array_keys($manifest->entries))->toBe(['resources/js/app.ts'])
        ->and($events->events)->toHaveCount(1)
        ->and($events->events[0])->toBeInstanceOf(Marko\Vite\Events\ManifestLoaded::class)
        ->and($events->events[0]->manifest)->toBe($manifest);
})->group('vite');

test('manifest repository throws for invalid json', function (): void {
    mkdir($this->tempDirectory . '/public/build', 0777, true);
    file_put_contents($this->tempDirectory . '/public/build/manifest.json', '{invalid');

    $events = new class () implements EventDispatcherInterface {
        public function dispatch(Event $event): void {}
    };

    $repository = new ManifestRepository(viteSupportConfig($this->tempDirectory), $events);

    expect(fn () => $repository->manifest())
        ->toThrow(ManifestNotFoundException::class);
})->group('vite');

test('tag renderer escapes attributes and dispatches render events', function (): void {
    $events = new class () implements EventDispatcherInterface {
        public array $events = [];

        public function dispatch(Event $event): void
        {
            $this->events[] = $event;
        }
    };

    $renderer = new TagRenderer($events);
    $collection = new ResolvedEntrypointCollection(
        entrypoints: ['resources/js/app.ts'],
        preloads: [new ResolvedAsset('resources/js/app.ts', '/build/chunk.js?x="1"', 'preload', false)],
        styles: [new ResolvedAsset('resources/js/app.ts', '/build/app.css?theme="light"', 'style', false)],
        scripts: [new ResolvedAsset('resources/js/app.ts', '/build/app.js?m="1"', 'script', false)],
        development: false,
    );

    $styles = $renderer->renderStyles($collection);
    $scripts = $renderer->renderScripts($collection);

    expect($styles)->toContain('modulepreload')
        ->toContain('/build/chunk.js?x=&quot;1&quot;')
        ->toContain('/build/app.css?theme=&quot;light&quot;')
        ->and($scripts)->toContain('<script type="module" src="/build/app.js?m=&quot;1&quot;"></script>')
        ->and($events->events)->toHaveCount(2)
        ->and($events->events[0])->toBeInstanceOf(Marko\Vite\Events\AssetTagsRendered::class)
        ->and($events->events[0]->kind)->toBe('styles')
        ->and($events->events[1]->kind)->toBe('scripts');
})->group('vite');

test(
    'asset url generator default entrypoint provider view helper and config normalization work together',
    function (): void {
        $config = ViteConfig::fromArray([
            'build_directory' => 'assets',
            'assets_base_url' => 'https://cdn.example.com/static/',
            'default_entrypoints' => ['resources/js/admin.ts'],
            'root_entrypoint_path' => 'resources/js/app.ts',
            'hot_file_path' => 'public/hot',
            'manifest_path' => 'public/build/manifest.json',
            'dev_process_file_path' => '.marko/dev.json',
        ], '/project');
    
        $generator = new AssetUrlGenerator($config);
        $provider = new DefaultEntrypointProvider($config);
        $manager = new class () implements Marko\Vite\Contracts\ViteManagerInterface {
            public array $calls = [];
    
            public function isDevelopment(): bool
            {
                return false;
            }
    
            public function resolve(string|array|null $entrypoints = null): ResolvedEntrypointCollection
            {
                $this->calls[] = ['resolve', $entrypoints];
    
                return new ResolvedEntrypointCollection([], [], [], [], false);
            }
    
            public function tags(string|array|null $entrypoints = null): string
            {
                $this->calls[] = ['tags', $entrypoints];
    
                return 'tags:' . json_encode($entrypoints);
            }
    
            public function scripts(string|array|null $entrypoints = null): string
            {
                $this->calls[] = ['scripts', $entrypoints];
    
                return 'scripts:' . json_encode($entrypoints);
            }
    
            public function styles(string|array|null $entrypoints = null): string
            {
                $this->calls[] = ['styles', $entrypoints];
    
                return 'styles:' . json_encode($entrypoints);
            }
        };
    
        $helper = new ViteViewHelper($manager);
    
        expect($generator->generate('/app.js'))->toBe('https://cdn.example.com/static/assets/app.js')
            ->and($provider->entrypoints())->toBe(['resources/js/admin.ts'])
            ->and($config->hotFilePath)->toBe('/project/public/hot')
            ->and($config->manifestPath)->toBe('/project/public/build/manifest.json')
            ->and($helper->tags('resources/js/app.ts'))->toBe('tags:"resources\/js\/app.ts"')
            ->and($helper->scripts(['resources/js/app.ts']))->toBe('scripts:["resources\/js\/app.ts"]')
            ->and($helper->styles())->toBe('styles:null');
    }
)->group('vite');

test('dev server resolver prefers hot file contents and rejects invalid urls', function (): void {
    mkdir($this->tempDirectory . '/public', 0777, true);
    file_put_contents($this->tempDirectory . '/public/hot', 'not-a-url');

    $resolver = new DevServerResolver(viteSupportConfig($this->tempDirectory));

    expect($resolver->isDevelopment())->toBeTrue()
        ->and(fn () => $resolver->resolve())->toThrow(DevServerUnavailableException::class);
})->group('vite');
