<?php

declare(strict_types=1);

use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Scheduler\Command\RunBackgroundCommand;
use Marko\Scheduler\Exceptions\SchedulerException;
use Marko\Scheduler\Mutex\FileTaskMutex;
use Marko\Scheduler\Mutex\TaskMutexInterface;
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
    $this->mutexName = 'schedule-' . sha1('* * * * *app:prune --days=30');
    // Another process's view of the same mutex directory.
    $this->otherProcess = new FileTaskMutex($this->mutexDirectory, $this->clock);
    $this->ran = [];
    $this->heldDuringRun = null;
    $this->exitCode = 0;
    [, $this->commandRunner] = SchedulerFactory::commands([
        'app:prune' => function (Input $input, Output $output): int {
            $this->ran[] = [$input->getOption('days'), $input->getArguments()];
            $this->heldDuringRun = $this->otherProcess->exists($this->mutexName);
            $output->writeLine('Pruned 3 rows.');

            return $this->exitCode;
        },
        'app:throws' => function (): never {
            throw new RuntimeException('Engine unreachable');
        },
    ]);
    $this->command = new RunBackgroundCommand(
        new FileTaskMutex($this->mutexDirectory, $this->clock),
        $this->commandRunner,
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
    $exitCode = ($this->execute)([
        "--schedule-mutex=$this->mutexName",
        '--schedule-mutex-expires=1800',
        '--',
        'app:prune',
        '--days=30',
        'users',
    ]);

    expect($exitCode)->toBe(0)
        ->and($this->ran)->toBe([['30', ['users']]])
        ->and($this->heldDuringRun)->toBeTrue()
        ->and($this->otherProcess->exists($this->mutexName))->toBeFalse()
        ->and(($this->outputContent)())->toBe("Pruned 3 rows.\n");
});

it('takes the mutex by the name and expiry on its command line', function (): void {
    $mutex = new class () implements TaskMutexInterface
    {
        /** @var list<array{string, int}> */
        public array $acquired = [];

        /** @var list<string> */
        public array $released = [];

        public function acquire(
            string $name,
            int $expiresAfterSeconds,
        ): bool {
            $this->acquired[] = [$name, $expiresAfterSeconds];

            return true;
        }

        public function release(
            string $name,
        ): void {
            $this->released[] = $name;
        }

        public function exists(
            string $name,
        ): bool {
            return false;
        }
    };

    $exitCode = new RunBackgroundCommand($mutex, $this->commandRunner)->execute(
        new Input(
            ['marko', RunBackgroundCommand::NAME, '--schedule-mutex=schedule-abc', '--schedule-mutex-expires=5400', '--', 'app:prune'],
        ),
        $this->output,
    );

    expect($exitCode)->toBe(0)
        ->and($mutex->acquired)->toBe([['schedule-abc', 5400]])
        ->and($mutex->released)->toBe(['schedule-abc']);
});

it('skips the command while another process holds the mutex', function (): void {
    expect($this->otherProcess->acquire($this->mutexName, 3600))->toBeTrue();

    try {
        $exitCode = ($this->execute)([
            "--schedule-mutex=$this->mutexName",
            '--schedule-mutex-expires=1800',
            '--',
            'app:prune',
            '--days=30',
        ]);
    } finally {
        $this->otherProcess->release($this->mutexName);
    }

    expect($exitCode)->toBe(0)
        ->and($this->ran)->toBe([])
        ->and(($this->outputContent)())->toBe("Skipped (still running): app:prune --days=30\n");
});

it('returns the exit code of the command', function (): void {
    $this->exitCode = 3;

    expect(($this->execute)(["--schedule-mutex=$this->mutexName", '--schedule-mutex-expires=60', '--', 'app:prune']))
        ->toBe(3)
        ->and($this->otherProcess->exists($this->mutexName))->toBeFalse();
});

it('releases the mutex when the command throws', function (): void {
    expect(
        fn () => ($this->execute)(["--schedule-mutex=$this->mutexName", '--schedule-mutex-expires=60', '--', 'app:throws']),
    )
        ->toThrow(RuntimeException::class, 'Engine unreachable')
        ->and($this->otherProcess->exists($this->mutexName))->toBeFalse();
});

it('throws when the mutex option is missing', function (): void {
    expect(fn () => ($this->execute)(['--schedule-mutex-expires=60', '--', 'app:prune']))
        ->toThrow(SchedulerException::class, 'schedule:run-background requires --schedule-mutex=<value>.');
});

it('throws when the mutex expiry is missing or invalid', function (array $tokens, string $given): void {
    expect(fn () => ($this->execute)(["--schedule-mutex=$this->mutexName", ...$tokens, '--', 'app:prune']))
        ->toThrow(
            SchedulerException::class,
            "schedule:run-background requires --schedule-mutex-expires=<seconds> with a whole number of seconds of at least 1, '$given' given.",
        );

    expect($this->ran)->toBe([]);
})->with([
    'missing' => [[], ''],
    'zero' => [['--schedule-mutex-expires=0'], '0'],
    'not a number' => [['--schedule-mutex-expires=soon'], 'soon'],
]);

it('throws when no command is given', function (): void {
    expect(fn () => ($this->execute)(["--schedule-mutex=$this->mutexName", '--schedule-mutex-expires=60', '--']))
        ->toThrow(SchedulerException::class, 'schedule:run-background was not given a command to run.');
});

it('refuses a mutex name that is not a safe file name', function (): void {
    expect(
        fn () => ($this->execute)(['--schedule-mutex=../../etc/passwd', '--schedule-mutex-expires=60', '--', 'app:prune']),
    )
        ->toThrow(SchedulerException::class, "'../../etc/passwd' is not a valid scheduler mutex name.");

    expect($this->ran)->toBe([]);
});
