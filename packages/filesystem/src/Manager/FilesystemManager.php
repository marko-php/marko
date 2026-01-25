<?php

declare(strict_types=1);

namespace Marko\Filesystem\Manager;

use JsonException;
use Marko\Core\Container\ContainerInterface;
use Marko\Filesystem\Config\FilesystemConfig;
use Marko\Filesystem\Contracts\FilesystemInterface;
use Marko\Filesystem\Discovery\DriverRegistry;
use Marko\Filesystem\Exceptions\FilesystemException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class FilesystemManager
{
    /**
     * @var array<string, FilesystemInterface>
     */
    private array $disks = [];

    public function __construct(
        private readonly FilesystemConfig $config,
        private readonly DriverRegistry $driverRegistry,
        private readonly ContainerInterface $container,
    ) {}

    /**
     * @throws FilesystemException|JsonException
     */
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
     * @param array<string, mixed> $config
     * @throws FilesystemException|JsonException
     */
    private function createDisk(
        array $config,
    ): FilesystemInterface {
        $driver = $config['driver'] ?? throw new FilesystemException(
            message: 'Missing driver in disk config',
            context: json_encode($config, JSON_THROW_ON_ERROR),
            suggestion: 'Add a "driver" key to your disk configuration',
        );

        $factoryClass = $this->driverRegistry->get($driver);

        try {
            $factory = $this->container->get($factoryClass);
        } catch (NotFoundExceptionInterface|ContainerExceptionInterface $e) {
            throw new FilesystemException(
                message: "Failed to create filesystem factory: $factoryClass",
                context: $e->getMessage(),
                suggestion: 'Ensure the driver package is installed and the factory class exists',
            );
        }

        return $factory->create($config);
    }
}
