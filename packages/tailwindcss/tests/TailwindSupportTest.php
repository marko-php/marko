<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\ConfigMerger;
use Marko\Core\Container\Container;
use Marko\Core\Event\Event;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\TailwindCss\ContentPathCollector;
use Marko\TailwindCss\Contracts\ContentPathProviderInterface;
use Marko\TailwindCss\Contracts\TailwindEntrypointProviderInterface;
use Marko\TailwindCss\DefaultContentPathProvider;
use Marko\TailwindCss\Plugin\ViteManagerPlugin;
use Marko\TailwindCss\TailwindAssetRegistry;
function tailwindSupportConfig(array $overrides = []): ConfigRepositoryInterface
{
    $base = [
        'enabled' => true,
        'auto_include_with_vite' => true,
        'entrypoints' => [
            'css' => 'resources/css/app.css',
        ],
        'content_paths' => [
            'resources/views/**/*.latte',
        ],
        'extra_content_paths' => [],
        'content_path_providers' => [],
    ];

    return new ConfigRepository([
        'tailwindcss' => (new ConfigMerger())->merge($base, $overrides),
    ]);
}

test('content path collector returns no paths when tailwind is disabled', function (): void {
    $config = tailwindSupportConfig(['enabled' => false]);
    $container = new Container();
    $container->instance(ConfigRepositoryInterface::class, $config);

    $collector = new ContentPathCollector(
        new DefaultContentPathProvider($config),
        $config,
        $container,
    );

    expect($collector->collect())->toBe([]);
})->group('tailwindcss');

test('content path collector throws when a configured provider is invalid', function (): void {
    $config = tailwindSupportConfig([
        'content_path_providers' => [
            stdClass::class,
        ],
    ]);
    $container = new Container();
    $container->instance(ConfigRepositoryInterface::class, $config);
    $container->instance(stdClass::class, new stdClass());

    $collector = new ContentPathCollector(
        new DefaultContentPathProvider($config),
        $config,
        $container,
    );

    expect(fn () => $collector->collect())->toThrow(\RuntimeException::class);
})->group('tailwindcss');

test('vite manager plugin does not auto include tailwind entrypoints when disabled or opted out', function (): void {
    $entrypointProvider = new class () implements TailwindEntrypointProviderInterface {
        public function entrypoints(): array
        {
            return ['resources/css/app.css'];
        }
    };
    $defaults = new class () implements Marko\Vite\Contracts\DefaultEntrypointProviderInterface {
        public function entrypoints(): array
        {
            return ['resources/js/app.ts'];
        }
    };
    $events = new class () implements EventDispatcherInterface {
        public function dispatch(Event $event): void
        {
            if ($event instanceof Marko\TailwindCss\Events\TailwindAssetsRegistering) {
                $event->registerEntrypoint('resources/css/app.css');
            }
        }
    };

    $disabledPlugin = new ViteManagerPlugin(
        tailwindSupportConfig(['enabled' => false]),
        new TailwindAssetRegistry($events),
        $defaults,
    );
    $optedOutPlugin = new ViteManagerPlugin(
        tailwindSupportConfig(['auto_include_with_vite' => false]),
        new TailwindAssetRegistry($events),
        $defaults,
    );

    expect($disabledPlugin->beforeTags())->toBe([['resources/js/app.ts']])
        ->and($optedOutPlugin->beforeResolve('resources/js/custom.ts'))->toBe([['resources/js/custom.ts']]);
})->group('tailwindcss');

test('tailwind asset registry de duplicates event supplied paths and entrypoints', function (): void {
    $registry = new TailwindAssetRegistry(new class () implements EventDispatcherInterface {
        public function dispatch(Event $event): void
        {
            if (!$event instanceof Marko\TailwindCss\Events\TailwindAssetsRegistering) {
                return;
            }

            $event->registerContentPath('resources/views/**/*.latte');
            $event->registerContentPath('resources/views/**/*.latte');
            $event->registerEntrypoint('resources/css/app.css');
            $event->registerEntrypoint('resources/css/app.css');
        }
    });

    expect($registry->contentPaths())->toBe(['resources/views/**/*.latte'])
        ->and($registry->entrypoints())->toBe(['resources/css/app.css']);
})->group('tailwindcss');
