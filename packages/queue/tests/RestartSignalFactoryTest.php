<?php

declare(strict_types=1);

use Marko\Cache\CacheOperations;
use Marko\Cache\Config\CacheConfig;
use Marko\Cache\Contracts\CacheInterface;
use Marko\Cache\Exceptions\NoDriverException as CacheNoDriverException;
use Marko\Cache\Memory\Driver\ArrayCacheDriver;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Path\ProjectPaths;
use Marko\Queue\CacheRestartSignal;
use Marko\Queue\Exceptions\QueueException;
use Marko\Queue\FileRestartSignal;
use Marko\Queue\QueueConfig;
use Marko\Queue\RestartSignalFactory;
use Marko\Queue\RestartSignalInterface;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;

function createRestartSignalContainer(
    string $driver,
    bool $withCache = true,
): Container {
    $container = new Container();
    $container->instance(ContainerInterface::class, $container);
    $container->instance(ConfigRepositoryInterface::class, new FakeConfigRepository([
        'queue.restart_signal_driver' => $driver,
        'queue.restart_signal' => 'storage/queue/restart',
    ]));
    $container->instance(ProjectPaths::class, new ProjectPaths(sys_get_temp_dir()));

    if ($withCache) {
        $container->instance(CacheInterface::class, new ArrayCacheDriver(new CacheConfig(new FakeConfigRepository([
            'cache.path' => sys_get_temp_dir(),
            'cache.default_ttl' => 60,
            'cache.driver' => 'array',
        ])), new FakeClock(), new CacheOperations()));
    }

    return $container;
}

describe('RestartSignalFactory', function (): void {
    it('creates the file restart signal when queue.restart_signal_driver is file', function (): void {
        $container = createRestartSignalContainer('file');

        expect($container->get(RestartSignalFactory::class)->create())->toBeInstanceOf(FileRestartSignal::class);
    });

    it('creates the cache restart signal when queue.restart_signal_driver is cache', function (): void {
        $container = createRestartSignalContainer('cache');

        expect($container->get(RestartSignalFactory::class)->create())->toBeInstanceOf(CacheRestartSignal::class);
    });

    it('throws naming the valid drivers for an unknown queue.restart_signal_driver', function (): void {
        $container = createRestartSignalContainer('redis');

        try {
            $container->get(RestartSignalFactory::class)->create();
            $this->fail('An unknown driver must throw.');
        } catch (QueueException $e) {
            expect($e->getMessage())->toContain("'redis'")
                ->and($e->getMessage())->toContain('queue.restart_signal_driver')
                ->and($e->getSuggestion())->toContain('file')
                ->and($e->getSuggestion())->toContain('cache');
        }
    });

    it('throws telling you to install marko/cache when the cache driver is chosen without it', function (): void {
        $container = createRestartSignalContainer('cache');
        $factory = new class ($container, $container->get(QueueConfig::class)) extends RestartSignalFactory
        {
            protected function cacheContractInstalled(): bool
            {
                return false;
            }
        };

        try {
            $factory->create();
            $this->fail('The cache driver without marko/cache must throw.');
        } catch (QueueException $e) {
            expect($e->getMessage())->toContain('marko/cache is not installed')
                ->and($e->getSuggestion())->toContain('composer require marko/cache marko/cache-redis')
                ->and($e->getSuggestion())->toContain('queue.restart_signal_driver');
        }
    });

    it('throws a queue exception wrapping the cache error when no cache driver is bound', function (): void {
        $container = createRestartSignalContainer('cache', withCache: false);

        try {
            $container->get(RestartSignalFactory::class)->create();
            $this->fail('The cache driver without a bound cache driver must throw.');
        } catch (QueueException $e) {
            expect($e->getMessage())->toContain('no cache driver')
                ->and($e->getPrevious())->toBeInstanceOf(CacheNoDriverException::class)
                ->and($e->getSuggestion())->toContain('marko/cache-redis');
        }
    });

    it('is what module.php resolves RestartSignalInterface through', function (): void {
        $module = require dirname(__DIR__) . '/module.php';
        $container = createRestartSignalContainer('cache');

        expect($module['bindings'][RestartSignalInterface::class]($container))
            ->toBeInstanceOf(CacheRestartSignal::class);
    });
});
