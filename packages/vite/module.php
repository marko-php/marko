<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Path\ProjectPaths;
use Marko\Vite\Vite;

return [
    'enabled' => true,
    'sequence' => [
        'after' => ['marko/config'],
    ],
    'bindings' => [
        Vite::class => function (ContainerInterface $container): Vite {
            $config = $container->get(ConfigRepositoryInterface::class);
            $paths = $container->get(ProjectPaths::class);

            if (! $config instanceof ConfigRepositoryInterface) {
                throw new RuntimeException('Config repository binding must implement '.ConfigRepositoryInterface::class);
            }

            if (! $paths instanceof ProjectPaths) {
                throw new RuntimeException('Project paths binding must resolve to '.ProjectPaths::class);
            }

            return new Vite(
                $config,
                $paths,
            );
        },
    ],
    'singletons' => [
        Vite::class,
    ],
];
