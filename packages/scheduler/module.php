<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Core\Path\ProjectPaths;
use Marko\Scheduler\Mutex\FileTaskMutex;
use Marko\Scheduler\Mutex\TaskMutexInterface;
use Marko\Scheduler\Process\BackgroundCommandLauncherInterface;
use Marko\Scheduler\Process\ProcOpenBackgroundCommandLauncher;
use Marko\Scheduler\Schedule;
use Psr\Clock\ClockInterface;

return [
    'bindings' => [
        TaskMutexInterface::class => function (ContainerInterface $container): TaskMutexInterface {
            return new FileTaskMutex(
                directory: $container->get(ProjectPaths::class)->base . '/storage/framework',
                clock: $container->get(ClockInterface::class),
            );
        },
        BackgroundCommandLauncherInterface::class => function (ContainerInterface $container): BackgroundCommandLauncherInterface {
            $base = $container->get(ProjectPaths::class)->base;

            return new ProcOpenBackgroundCommandLauncher(
                phpBinary: PHP_BINARY,
                markoBinary: $base . '/vendor/bin/marko',
                workingDirectory: $base,
            );
        },
    ],
    'singletons' => [
        Schedule::class,
        TaskMutexInterface::class,
    ],
];
