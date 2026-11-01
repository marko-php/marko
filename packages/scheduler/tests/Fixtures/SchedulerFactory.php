<?php

declare(strict_types=1);

namespace Marko\Scheduler\Tests\Fixtures;

use Closure;
use Marko\Cache\Contracts\LockFactoryInterface;
use Marko\Cache\Contracts\LockStoreInterface;
use Marko\Cache\Lock\LockFactory;
use Marko\Core\Command\CommandDefinition;
use Marko\Core\Command\CommandRegistry;
use Marko\Core\Command\CommandRunner;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Core\Container\Container;
use Marko\Core\Exceptions\CommandException;
use Marko\Core\Sleep\UsleepSleeper;
use Marko\Scheduler\Mutex\DistributedLockResolver;
use Marko\Scheduler\Mutex\LockTaskServerClaim;
use Marko\Scheduler\Mutex\TaskMutexInterface;
use Marko\Scheduler\Mutex\TaskServerClaimInterface;
use Marko\Scheduler\Process\BackgroundCommandLauncherInterface;
use Marko\Scheduler\Schedule;
use Marko\Scheduler\ScheduleRunner;
use Psr\Clock\ClockInterface;

/**
 * Builds schedules, runners and command registries for scheduler tests.
 */
class SchedulerFactory
{
    /**
     * A command registry and runner holding one RecordingCommand per name.
     *
     * @param array<string, Closure(Input, Output): int> $commands Handlers keyed by command name
     * @return array{CommandRegistry, CommandRunner}
     * @throws CommandException
     */
    public static function commands(
        array $commands = [],
    ): array {
        $container = new Container();
        $registry = new CommandRegistry();

        foreach ($commands as $name => $handler) {
            $id = "scheduler.test.command.$name";
            $container->instance($id, new RecordingCommand($handler));
            $registry->register(new CommandDefinition(commandClass: $id, name: $name));
        }

        return [$registry, new CommandRunner($container, $registry)];
    }

    public static function schedule(
        ?CommandRegistry $commandRegistry = null,
    ): Schedule {
        return new Schedule($commandRegistry ?? new CommandRegistry());
    }

    /**
     * @throws CommandException
     */
    public static function runner(
        Schedule $schedule,
        TaskMutexInterface $taskMutex,
        ?CommandRunner $commandRunner = null,
        ?BackgroundCommandLauncherInterface $backgroundCommandLauncher = null,
        ?TaskServerClaimInterface $taskServerClaim = null,
    ): ScheduleRunner {
        return new ScheduleRunner(
            $schedule,
            $taskMutex,
            $commandRunner ?? self::commands()[1],
            $backgroundCommandLauncher ?? new RecordingBackgroundCommandLauncher(),
            $taskServerClaim ?? new LockTaskServerClaim(new DistributedLockResolver(new Container())),
        );
    }

    /**
     * A lock resolver whose container holds a LockFactory on $store, as marko/cache and a
     * driver package would bind them.
     */
    public static function lockResolver(
        LockStoreInterface $store,
        ClockInterface $clock,
    ): DistributedLockResolver {
        $container = new Container();
        $container->instance(LockStoreInterface::class, $store);
        $container->instance(LockFactoryInterface::class, new LockFactory($store, $clock, new UsleepSleeper()));

        return new DistributedLockResolver($container);
    }
}
