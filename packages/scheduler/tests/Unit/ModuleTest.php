<?php

declare(strict_types=1);

use Marko\Clock\SystemClock;
use Marko\Core\Command\CommandDefinition;
use Marko\Core\Command\CommandRegistry;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Core\Container\BindingRegistry;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Module\ManifestParser;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Path\ProjectPaths;
use Marko\Scheduler\Command\RunBackgroundCommand;
use Marko\Scheduler\Command\RunScheduleCommand;
use Marko\Scheduler\Command\ScheduleWorkCommand;
use Marko\Scheduler\Exceptions\SchedulerException;
use Marko\Scheduler\Mutex\LockTaskMutex;
use Marko\Scheduler\Mutex\LockTaskServerClaim;
use Marko\Scheduler\Mutex\TaskServerClaimInterface;
use Marko\Scheduler\Process\BackgroundCommandLauncherInterface;
use Marko\Scheduler\Process\ProcOpenBackgroundCommandLauncher;
use Marko\Scheduler\Schedule;
use Marko\Scheduler\Tests\Fixtures\RecordingCommand;
use Psr\Clock\ClockInterface;

/**
 * Build a container wired exactly as the Application does: the real scheduler
 * manifest registered through BindingRegistry, then module boot callbacks called.
 */
function bootSchedulerContainer(
    string $basePath,
    ?Closure $appBoot = null,
    ?CommandRegistry $commandRegistry = null,
): Container {
    $container = new Container();
    // The Application binds the container and the discovered command registry before boot.
    $container->instance(ContainerInterface::class, $container);
    $container->instance(CommandRegistry::class, $commandRegistry ?? new CommandRegistry());
    $container->instance(ProjectPaths::class, new ProjectPaths($basePath));
    // marko/clock's module binds the system clock; the scheduler requires it.
    $container->instance(ClockInterface::class, new SystemClock());

    $registry = new BindingRegistry($container);
    $schedulerManifest = new ManifestParser()->parse(dirname(__DIR__, 2));
    $registry->registerModule($schedulerManifest);

    $appManifest = new ModuleManifest(
        name: 'app/tasks',
        version: '1.0.0',
        source: 'app',
        boot: $appBoot,
    );
    $registry->registerModule($appManifest);

    foreach ([$schedulerManifest, $appManifest] as $manifest) {
        if ($manifest->boot !== null) {
            $container->call($manifest->boot);
        }
    }

    return $container;
}

beforeEach(function (): void {
    $this->basePath = sys_get_temp_dir() . '/marko-scheduler-module-' . bin2hex(random_bytes(6));
    mkdir($this->basePath, 0755, true);
});

afterEach(function (): void {
    if (is_dir($this->basePath)) {
        exec('rm -rf ' . escapeshellarg($this->basePath));
    }
});

it('declares Schedule as a singleton in module.php', function (): void {
    $config = require dirname(__DIR__, 2) . '/module.php';

    expect($config)->toHaveKey('singletons')
        ->and($config['singletons'])->toContain(Schedule::class);
});

it('resolves the same Schedule instance from the container on every request', function (): void {
    $container = bootSchedulerContainer($this->basePath);

    expect($container->get(Schedule::class))->toBe($container->get(Schedule::class));
});

it('executes a task registered through a module boot callback when schedule:run runs', function (): void {
    $executed = false;
    $container = bootSchedulerContainer(
        $this->basePath,
        function (Schedule $schedule) use (&$executed): void {
            $schedule->call(function () use (&$executed): void {
                $executed = true;
            })->everyMinute()->description('Boot-registered task');
        },
    );

    $stream = fopen('php://memory', 'r+');
    $exitCode = $container->get(RunScheduleCommand::class)
        ->execute(new Input(['marko', 'schedule:run']), new Output($stream));
    rewind($stream);
    $output = stream_get_contents($stream);

    expect($executed)->toBeTrue()
        ->and($output)->toContain('Executed: Boot-registered task')
        ->and($exitCode)->toBe(0);
});

it('resolves schedule:work from the container', function (): void {
    $container = bootSchedulerContainer($this->basePath);

    expect($container->get(ScheduleWorkCommand::class))->toBeInstanceOf(ScheduleWorkCommand::class);
});

it('binds the background command launcher to ProcOpenBackgroundCommandLauncher', function (): void {
    $container = bootSchedulerContainer($this->basePath);

    expect($container->get(BackgroundCommandLauncherInterface::class))
        ->toBeInstanceOf(ProcOpenBackgroundCommandLauncher::class);
});

it('resolves schedule:run-background from the container', function (): void {
    $container = bootSchedulerContainer($this->basePath);

    expect($container->get(RunBackgroundCommand::class))->toBeInstanceOf(RunBackgroundCommand::class);
});

it('runs a command scheduled in a module boot callback when schedule:run runs', function (): void {
    $ran = [];
    $registry = new CommandRegistry();
    $registry->register(new CommandDefinition(commandClass: RecordingCommand::class, name: 'app:prune'));
    $container = bootSchedulerContainer(
        $this->basePath,
        function (Schedule $schedule): void {
            $schedule->command('app:prune', ['--days=30'])->everyMinute();
        },
        $registry,
    );
    $container->instance(RecordingCommand::class, new RecordingCommand(function (Input $input) use (&$ran): int {
        $ran[] = $input->getOption('days');

        return 0;
    }));

    $stream = fopen('php://memory', 'r+');
    $exitCode = $container->get(RunScheduleCommand::class)
        ->execute(new Input(['marko', 'schedule:run']), new Output($stream));
    rewind($stream);

    expect($ran)->toBe(['30'])
        ->and(stream_get_contents($stream))->toContain('Executed: app:prune --days=30')
        ->and($exitCode)->toBe(0);
});

it('binds the task server claim to LockTaskServerClaim without needing a lock store', function (): void {
    $container = bootSchedulerContainer($this->basePath);

    expect($container->get(TaskServerClaimInterface::class))->toBeInstanceOf(LockTaskServerClaim::class)
        ->and($container->get(RunScheduleCommand::class))->toBeInstanceOf(RunScheduleCommand::class);
});

it('fails schedule:run up front when an onOneServer task has no lock store bound', function (): void {
    $container = bootSchedulerContainer(
        $this->basePath,
        function (Schedule $schedule): void {
            $schedule->call(fn (): null => null)->everyMinute()->description('Send digest')->onOneServer();
        },
    );

    expect(fn () => $container->get(RunScheduleCommand::class)
        ->execute(new Input(['marko', 'schedule:run']), new Output(fopen('php://memory', 'r+'))))
        ->toThrow(
            SchedulerException::class,
            "Scheduled task 'Send digest' uses onOneServer(), but no cache lock store can be resolved.",
        );
});

it('fails to resolve LockTaskMutex when no lock store is bound', function (): void {
    $container = bootSchedulerContainer($this->basePath);

    $container->get(LockTaskMutex::class);
})->throws(
    SchedulerException::class,
    'LockTaskMutex is bound to TaskMutexInterface, but no cache lock store can be resolved.',
);
