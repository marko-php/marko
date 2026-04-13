<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\ConfigMerger;
use Marko\Core\Container\Container;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Core\Path\ProjectPaths;
use Marko\TailwindCss\ContentPathCollector;
use Marko\TailwindCss\Contracts\TailwindEntrypointProviderInterface;
use Marko\TailwindCss\DefaultContentPathProvider;
use Marko\TailwindCss\DefaultTailwindEntrypointProvider;
use Marko\TailwindCss\Events\TailwindAssetsRegistering;
use Marko\TailwindCss\Observer\RegisterTailwindAssets;
use Marko\TailwindCss\Plugin\ViteManagerPlugin;
use Marko\TailwindCss\TailwindAssetRegistry;
use Marko\TailwindCss\TailwindPublisher;
use Marko\TailwindCss\Tests\Fixtures\AdditionalContentPathProvider;
use Marko\Vite\Contracts\DefaultEntrypointProviderInterface;
use Marko\Vite\ProjectFilePublisher;

function tailwindConfig(array $overrides = []): ConfigRepositoryInterface
{
    $base = [
        'enabled' => true,
        'entrypoints' => [
            'css' => 'resources/css/app.css',
        ],
        'auto_include_with_vite' => true,
        'content_paths' => [
            'app/**/*.php',
            'modules/**/*.php',
            'resources/views/**/*.php',
            'resources/views/**/*.latte',
            'resources/views/**/*.blade.php',
            'resources/js/**/*.{js,jsx,ts,tsx,vue,svelte}',
            'app/**/resources/views/**/*.php',
            'app/**/resources/views/**/*.latte',
            'app/**/resources/views/**/*.blade.php',
            'app/**/resources/js/**/*.{js,jsx,ts,tsx,vue,svelte}',
            'modules/**/resources/views/**/*.php',
            'modules/**/resources/views/**/*.latte',
            'modules/**/resources/views/**/*.blade.php',
            'modules/**/resources/js/**/*.{js,jsx,ts,tsx,vue,svelte}',
            'vendor/marko/**/resources/views/**/*.php',
            'vendor/marko/**/resources/views/**/*.latte',
            'vendor/marko/**/resources/views/**/*.blade.php',
            'vendor/marko/**/resources/js/**/*.{js,jsx,ts,tsx,vue,svelte}',
        ],
        'extra_content_paths' => [],
        'content_path_providers' => [],
    ];

    return new ConfigRepository([
        'tailwindcss' => (new ConfigMerger())->merge($base, $overrides),
    ]);
}

test('asset registry collects default content paths and entrypoints through the event observer', function (): void {
    $config = tailwindConfig();
    $container = new Container();
    $container->instance(ConfigRepositoryInterface::class, $config);
    $observer = new RegisterTailwindAssets(
        new ContentPathCollector(
            new DefaultContentPathProvider($config),
            $config,
            $container,
        ),
        new DefaultTailwindEntrypointProvider($config),
    );

    $dispatcher = new class ($observer) implements EventDispatcherInterface {
        public function __construct(
            private readonly RegisterTailwindAssets $observer,
        ) {}

        public function dispatch(Marko\Core\Event\Event $event): void
        {
            if ($event instanceof TailwindAssetsRegistering) {
                $this->observer->handle($event);
            }
        }
    };

    $registry = new TailwindAssetRegistry($dispatcher);

    expect($registry->entrypoints())->toBe(['resources/css/app.css']);
    expect($registry->contentPaths())->toBe([
        'app/**/*.php',
        'modules/**/*.php',
        'resources/views/**/*.php',
        'resources/views/**/*.latte',
        'resources/views/**/*.blade.php',
        'resources/js/**/*.{js,jsx,ts,tsx,vue,svelte}',
        'app/**/resources/views/**/*.php',
        'app/**/resources/views/**/*.latte',
        'app/**/resources/views/**/*.blade.php',
        'app/**/resources/js/**/*.{js,jsx,ts,tsx,vue,svelte}',
        'modules/**/resources/views/**/*.php',
        'modules/**/resources/views/**/*.latte',
        'modules/**/resources/views/**/*.blade.php',
        'modules/**/resources/js/**/*.{js,jsx,ts,tsx,vue,svelte}',
        'vendor/marko/**/resources/views/**/*.php',
        'vendor/marko/**/resources/views/**/*.latte',
        'vendor/marko/**/resources/views/**/*.blade.php',
        'vendor/marko/**/resources/js/**/*.{js,jsx,ts,tsx,vue,svelte}',
    ]);
})->group('tailwindcss');

test('content path collector merges configured providers, extra paths, and normalizes duplicates', function (): void {
    $config = tailwindConfig([
        'content_paths' => [
            './resources/views/**/*.latte',
            'modules/**/resources/views/**/*.latte',
        ],
        'extra_content_paths' => [
            'resources/frontend/**/*.{tsx,vue}',
            ' resources/views/**/*.latte ',
        ],
        'content_path_providers' => [
            'additional' => AdditionalContentPathProvider::class,
        ],
    ]);

    $container = new Container();
    $container->instance(ConfigRepositoryInterface::class, $config);

    $collector = new ContentPathCollector(
        new DefaultContentPathProvider($config),
        $config,
        $container,
    );

    expect($collector->collect())->toBe([
        'resources/views/**/*.latte',
        'modules/**/resources/views/**/*.latte',
        'resources/frontend/**/*.{tsx,vue}',
        'modules/inertiajs/resources/js/**/*.{jsx,tsx,vue,svelte}',
    ]);
})->group('tailwindcss');

test('vite manager plugin auto-includes the tailwind entrypoint', function (): void {
    $config = tailwindConfig();
    $entrypoints = new class () implements TailwindEntrypointProviderInterface {
        public function entrypoints(): array
        {
            return ['resources/css/app.css'];
        }
    };
    $defaults = new class () implements DefaultEntrypointProviderInterface {
        public function entrypoints(): array
        {
            return ['resources/js/app.ts'];
        }
    };
    $container = new Container();
    $container->instance(ConfigRepositoryInterface::class, $config);
    $observer = new RegisterTailwindAssets(
        new ContentPathCollector(
            new DefaultContentPathProvider($config),
            $config,
            $container,
        ),
        $entrypoints,
    );

    $dispatcher = new class ($observer) implements EventDispatcherInterface {
        public function __construct(
            private readonly RegisterTailwindAssets $observer,
        ) {}

        public function dispatch(Marko\Core\Event\Event $event): void
        {
            if ($event instanceof TailwindAssetsRegistering) {
                $this->observer->handle($event);
            }
        }
    };

    $plugin = new ViteManagerPlugin($config, new TailwindAssetRegistry($dispatcher), $defaults);

    expect($plugin->beforeResolve('resources/js/app.ts'))->toBe([[
        'resources/js/app.ts',
        'resources/css/app.css',
    ]]);
    expect($plugin->beforeTags('resources/js/app.ts'))->toBe([[
        'resources/js/app.ts',
        'resources/css/app.css',
    ]]);
    expect($plugin->beforeStyles('resources/js/app.ts'))->toBe([[
        'resources/js/app.ts',
        'resources/css/app.css',
    ]]);
})->group('tailwindcss');

test('vite manager plugin preserves vite default js entrypoint when none is provided', function (): void {
    $config = tailwindConfig();
    $entrypoints = new class () implements TailwindEntrypointProviderInterface {
        public function entrypoints(): array
        {
            return ['resources/css/app.css'];
        }
    };
    $defaults = new class () implements DefaultEntrypointProviderInterface {
        public function entrypoints(): array
        {
            return ['resources/js/app.ts'];
        }
    };
    $container = new Container();
    $container->instance(ConfigRepositoryInterface::class, $config);
    $observer = new RegisterTailwindAssets(
        new ContentPathCollector(
            new DefaultContentPathProvider($config),
            $config,
            $container,
        ),
        $entrypoints,
    );

    $dispatcher = new class ($observer) implements EventDispatcherInterface {
        public function __construct(
            private readonly RegisterTailwindAssets $observer,
        ) {}

        public function dispatch(Marko\Core\Event\Event $event): void
        {
            if ($event instanceof TailwindAssetsRegistering) {
                $this->observer->handle($event);
            }
        }
    };

    $plugin = new ViteManagerPlugin($config, new TailwindAssetRegistry($dispatcher), $defaults);

    expect($plugin->beforeResolve())->toBe([[
        'resources/js/app.ts',
        'resources/css/app.css',
    ]]);
})->group('tailwindcss');

test('default tailwind entrypoint is the application css file', function (): void {
    $provider = new DefaultTailwindEntrypointProvider(tailwindConfig());

    expect($provider->entrypoints())->toBe(['resources/css/app.css']);
})->group('tailwindcss');

test('tailwind publisher supports dry run for optional css overrides', function (): void {
    $directory = sys_get_temp_dir() . '/marko-tailwind-publisher-' . bin2hex(random_bytes(6));
    mkdir($directory, 0777, true);

    try {
        $config = tailwindConfig();
        $publisher = new TailwindPublisher(
            new DefaultTailwindEntrypointProvider($config),
            new ProjectFilePublisher(new ProjectPaths($directory)),
        );

        $result = $publisher->publishCssEntrypoint(dryRun: true);

        expect($result->status)->toBe('would_create');
        expect(file_exists($directory . '/resources/css/app.css'))->toBeFalse();
    } finally {
        if (is_dir($directory)) {
            rmdir($directory);
        }
    }
})->group('tailwindcss');
