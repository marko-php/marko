<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Core\Path\ProjectPaths;
use Marko\Scheduler\Mutex\FileTaskMutex;
use Marko\Scheduler\Mutex\LockTaskServerClaim;
use Marko\Scheduler\Mutex\TaskMutexInterface;
use Marko\Scheduler\Mutex\TaskServerClaimInterface;
use Marko\Scheduler\Process\BackgroundCommandLauncherInterface;
use Marko\Scheduler\Process\ProcOpenBackgroundCommandLauncher;
use Marko\Scheduler\Schedule;

return [
    'bindings' => [
        TaskMutexInterface::class => FileTaskMutex::class,
        // Resolves marko/cache's lock factory only when an onOneServer() task needs it.
        TaskServerClaimInterface::class => LockTaskServerClaim::class,
        BackgroundCommandLauncherInterface::class => function (ContainerInterface $container): BackgroundCommandLauncherInterface {
            $base = $container->get(ProjectPaths::class)->base;

            return new ProcOpenBackgroundCommandLauncher(
                phpBinary: PHP_BINARY,
                markoBinary: $base . '/vendor/bin/marko',
                workingDirectory: $base,
            );
        },
    ],
    'arguments' => [
        FileTaskMutex::class => [
            'directory' => fn (ContainerInterface $container): string => $container->get(
                ProjectPaths::class,
            )->base . '/storage/framework',
        ],
    ],
    'singletons' => [
        Schedule::class,
        TaskMutexInterface::class,
    ],
];
