<?php

declare(strict_types=1);

namespace Marko\Filesystem\Manager;

use Marko\Core\Container\ContainerInterface;
use Marko\Filesystem\Config\FilesystemConfig;
use Marko\Filesystem\Contracts\FilesystemInterface;
use Marko\Filesystem\Exceptions\FilesystemException;

class FilesystemManager
{
    /**
     * @var array<string, FilesystemInterface>
     */
    private array $disks = [];

    /**
     * @var array<string, class-string>
     */
    private array $factoryMap = [];

    public function __construct(
        private readonly FilesystemConfig $config,
        private readonly ContainerInterface $container,
    ) {}

    public function disk(
        ?string $name = null,
    ): FilesystemInterface {
        $name ??= $this->config->defaultDisk();

        if (!isset($this->disks[$name])) {
            $diskConfig = $this->config->getDisk($name);
            $this->disks[$name] = $this->createDisk($diskConfig);
        }

        return $this->disks[$name];
    }

    /**
     * Register a factory for a driver type.
     *
     * @param class-string $factoryClass
     */
    public function registerDriver(
        string $driver,
        string $factoryClass,
    ): void {
        $this->factoryMap[$driver] = $factoryClass;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createDisk(
        array $config,
    ): FilesystemInterface {
        $driver = $config['driver'] ?? throw new FilesystemException(
            message: 'Missing driver in disk config',
            context: json_encode($config, JSON_THROW_ON_ERROR),
            suggestion: 'Add a "driver" key to your disk configuration',
        );

        if (!isset($this->factoryMap[$driver])) {
            throw new FilesystemException(
                message: "Unknown filesystem driver: $driver",
                context: 'Available drivers depend on installed packages',
                suggestion: 'For local storage: composer require marko/filesystem-local',
            );
        }

        $factory = $this->container->get($this->factoryMap[$driver]);

        return $factory->create($config);
    }
}
