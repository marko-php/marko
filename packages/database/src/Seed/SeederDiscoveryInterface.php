<?php

declare(strict_types=1);

namespace Marko\Database\Seed;

/**
 * Interface for seeder discovery.
 */
interface SeederDiscoveryInterface
{
    /**
     * Discover seeders in vendor directories.
     *
     * @return array<SeederDefinition>
     */
    public function discoverInVendor(
        string $vendorPath,
    ): array;

    /**
     * Discover seeders in modules directories.
     *
     * @return array<SeederDefinition>
     */
    public function discoverInModules(
        string $modulesPath,
    ): array;

    /**
     * Discover seeders in app directories.
     *
     * @return array<SeederDefinition>
     */
    public function discoverInApp(
        string $appPath,
    ): array;
}
