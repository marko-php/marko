<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Core\Path\ProjectPaths;
use Marko\DevServer\Detection\DockerDetector;
use Marko\DevServer\Detection\FrontendDetector;
use Marko\DevServer\Process\PidFile;

return [
    'bindings' => [
        DockerDetector::class => function (ContainerInterface $container): DockerDetector {
            return new DockerDetector($container->get(ProjectPaths::class)->base);
        },
        FrontendDetector::class => function (ContainerInterface $container): FrontendDetector {
            return new FrontendDetector($container->get(ProjectPaths::class)->base);
        },
        PidFile::class => function (ContainerInterface $container): PidFile {
            return new PidFile($container->get(ProjectPaths::class)->base);
        },
    ],
];
