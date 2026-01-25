<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Core\Path\ProjectPaths;
use Marko\Database\Seed\SeederDiscovery;
use Marko\Database\Seed\SeederDiscoveryInterface;
use Marko\Database\Seed\SeederRunner;

return [
    'bindings' => [
        SeederDiscoveryInterface::class => SeederDiscovery::class,
        SeederRunner::class => function (ContainerInterface $container): SeederRunner {
            $discovery = $container->get(SeederDiscoveryInterface::class);
            $paths = $container->get(ProjectPaths::class);

            // Discover all seeder definitions
            $definitions = array_merge(
                $discovery->discoverInVendor($paths->vendor),
                $discovery->discoverInModules($paths->modules),
                $discovery->discoverInApp($paths->app),
            );

            // Instantiate each seeder via container (for DI)
            $seeders = [];
            foreach ($definitions as $definition) {
                $seeders[$definition->seederClass] = $container->get($definition->seederClass);
            }

            return new SeederRunner($seeders);
        },
    ],
];
