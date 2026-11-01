<?php

declare(strict_types=1);

use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Scheduler\Command\RunBackgroundCommand;
use Marko\Scheduler\Exceptions\SchedulerException;
use Marko\Scheduler\Mutex\FileTaskMutex;
use Marko\Scheduler\Tests\Fixtures\SchedulerFactory;
use Marko\Testing\Fake\FakeClock;

beforeEach(function (): void {
    $this->mutexDirectory = sys_get_temp_dir() . '/marko-scheduler-background-' . bin2hex(random_bytes(6));
    $this->clock = new FakeClock('2026-10-05 12:00:00');
    $this->stream = fopen('php://memory', 'r+');
    $this->output = new Output($this->stream);
    $this->outputContent = function (): string {
        rewind($this->stream);

        return stream_get_contents($this->stream);
    };
    // Another process's view of the same mutex directory.
    $this->otherProcess = new FileTaskMutex($this->mutexDirectory, $this->clock);
    $this->ran = [];
    $this->heldDuringRun = null;
    $this->exitCode = 0;
    [$registry, $commandRunner] = SchedulerFactory::commands([
        'app:prune' => function (Input $input, Output $output): int {
            $this->ran[] = [$input->getOption('days'), $input->getArguments()];
            $this->heldDuringRun = $this->otherProcess->exists($this->task);
            $output->writeLine('Pruned 3 rows.');

            return $this->exitCode;
        },
        'app:throws' => function (): never {
            throw new RuntimeException('Engine unreachable');
        },
    ]);
    $this->schedule = SchedulerFactory::schedule($registry);
    $this->task = $this->schedule->command('app:prune', ['--days=30'])->everyMinute()->withoutOverlapping(30);
    $this->command = new RunBackgroundCommand(
        $this->schedule,
        new FileTaskMutex($this->mutexDirectory, $this->clock),
        $commandRunner,
    );
    $this->execute = fn (array $tokens): int => $this->command->execute(
        new Input(['marko', RunBackgroundCommand::NAME, ...$tokens]),
        $this->output,
    );
});

afterEach(function (): void {
    if (is_dir($this->mutexDirectory)) {
        exec('rm -rf ' . escapeshellarg($this->mutexDirectory));
    }
});

it('holds the overlap mutex while the command runs and releases it when the command ends', function (): void {
    $exitCode = ($this->execute)(['--schedule-mutex=' . $this->task->mutexName(), '--', 'app:prune', '--days=30', 'users']);

    expect($exitCode)->toBe(0)
        ->and($this->ran)->toBe([['30', ['users']]])
        ->and($this->heldDuringRun)->toBeTrue()
        ->and($this->otherProcess->exists($this->task))->toBeFalse()
        ->and(($this->outputContent)())->toBe("Pruned 3 rows.\n");
});

it('skips the command while another process holds the mutex', function (): void {
    expect($this->otherProcess->acquire($this->task, 3600))->toBeTrue();

    try {
        $exitCode = ($this->execute)(['--schedule-mutex=' . $this->task->mutexName(), '--', 'app:prune', '--days=30']);
    } finally {
        $this->otherProcess->release($this->task);
    }

    expect($exitCode)->toBe(0)
        ->and($this->ran)->toBe([])
        ->and(($this->outputContent)())->toBe("Skipped (still running): app:prune --days=30\n");
});

it('returns the exit code of the command', function (): void {
    $this->exitCode = 3;

    expect(($this->execute)(['--schedule-mutex=' . $this->task->mutexName(), '--', 'app:prune']))->toBe(3)
        ->and($this->otherProcess->exists($this->task))->toBeFalse();
});

it('releases the mutex when the command throws', function (): void {
    $task = $this->schedule->command('app:throws')->everyMinute()->withoutOverlapping();

    expect(fn () => ($this->execute)(['--schedule-mutex=' . $task->mutexName(), '--', 'app:throws']))
        ->toThrow(RuntimeException::class, 'Engine unreachable')
        ->and($this->otherProcess->exists($task))->toBeFalse();
});

it('throws when the mutex option is missing', function (): void {
    expect(fn () => ($this->execute)(['--', 'app:prune']))
        ->toThrow(SchedulerException::class, 'schedule:run-background requires --schedule-mutex=<name>.');
});

it('throws when no command is given', function (): void {
    expect(fn () => ($this->execute)(['--schedule-mutex=' . $this->task->mutexName(), '--']))
        ->toThrow(SchedulerException::class, 'schedule:run-background was not given a command to run.');
});

it('throws when no overlap-protected task has the mutex name', function (): void {
    expect(fn () => ($this->execute)(['--schedule-mutex=schedule-unknown', '--', 'app:prune']))
        ->toThrow(
            SchedulerException::class,
            "No overlap-protected scheduled task has the mutex name 'schedule-unknown'.",
        );

    expect($this->ran)->toBe([]);
});
