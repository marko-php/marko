<?php

declare(strict_types=1);

namespace Marko\Filesystem\Discovery;

use Marko\Core\Module\ModuleRepositoryInterface;
use Marko\Filesystem\Contracts\FilesystemDriverFactoryInterface;
use Marko\Filesystem\Exceptions\FilesystemException;

/**
 * Discovers and stores filesystem driver factories indexed by driver name.
 */
readonly class DriverRegistry
{
    /** @var array<string, class-string<FilesystemDriverFactoryInterface>> */
    private array $drivers;

    /**
     * @throws FilesystemException
     */
    public function __construct(
        DriverDiscovery $discovery,
        ModuleRepositoryInterface $modules,
    ) {
        $this->drivers = $discovery->discover($modules->all());
    }

    /**
     * Get the factory class for a driver.
     *
     * @return class-string<FilesystemDriverFactoryInterface>
     * @throws FilesystemException
     */
    public function get(
        string $name,
    ): string {
        if (!$this->has($name)) {
            throw new FilesystemException(
                message: "Unknown filesystem driver: $name",
                context: 'Available drivers: ' . implode(', ', array_keys($this->drivers)),
                suggestion: 'For local storage: composer require marko/filesystem-local',
            );
        }

        return $this->drivers[$name];
    }

    public function has(
        string $name,
    ): bool {
        return isset($this->drivers[$name]);
    }

    /**
     * @return array<string, class-string<FilesystemDriverFactoryInterface>>
     */
    public function all(): array
    {
        return $this->drivers;
    }
}
