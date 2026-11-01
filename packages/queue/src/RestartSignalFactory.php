<?php

declare(strict_types=1);

namespace Marko\Queue;

use Marko\Cache\Contracts\CacheInterface;
use Marko\Cache\Exceptions\NoDriverException as CacheNoDriverException;
use Marko\Core\Container\ContainerInterface;
use Marko\Queue\Exceptions\QueueException;

/**
 * Builds the RestartSignalInterface implementation that queue.restart_signal_driver names.
 *
 * - file: FileRestartSignal, a file at queue.restart_signal (one host, or hosts that share a disk)
 * - cache: CacheRestartSignal, a key in the bound cache store (use marko/cache-redis across hosts)
 *
 * marko/cache is only a suggestion of marko/queue, so CacheRestartSignal is resolved only when
 * the cache driver is chosen, and a missing marko/cache or cache driver fails loudly here.
 */
class RestartSignalFactory
{
    public const string DRIVER_FILE = 'file';

    public const string DRIVER_CACHE = 'cache';

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly QueueConfig $config,
    ) {}

    /**
     * @throws QueueException When the driver is unknown, or the cache driver lacks marko/cache or a cache driver
     */
    public function create(): RestartSignalInterface
    {
        $driver = $this->config->restartSignalDriver();

        return match ($driver) {
            self::DRIVER_FILE => $this->container->get(FileRestartSignal::class),
            self::DRIVER_CACHE => $this->cacheRestartSignal(),
            default => throw QueueException::unknownRestartSignalDriver(
                $driver,
                [self::DRIVER_FILE, self::DRIVER_CACHE],
            ),
        };
    }

    /**
     * @throws QueueException
     */
    private function cacheRestartSignal(): CacheRestartSignal
    {
        if (!$this->cacheContractInstalled()) {
            throw QueueException::restartSignalCachePackageNotInstalled();
        }

        try {
            return $this->container->get(CacheRestartSignal::class);
        } catch (CacheNoDriverException $e) {
            throw QueueException::noRestartSignalCacheDriver($e);
        }
    }

    /**
     * Whether marko/cache, which owns CacheInterface, is installed.
     */
    protected function cacheContractInstalled(): bool
    {
        return interface_exists(CacheInterface::class);
    }
}
