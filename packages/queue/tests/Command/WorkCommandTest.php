<?php

declare(strict_types=1);

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Queue\Command\WorkCommand;
use Marko\Queue\Tests\Command\Helpers;
use Marko\Queue\WorkerInterface;

/**
 * Helper to execute WorkCommand and return output.
 *
 * @param array<string> $args
 *
 * @return array{output: string, exitCode: int}
 */
function executeWorkCommand(
    WorkCommand $command,
    array $args = ['marko', 'queue:work'],
): array {
    ['stream' => $stream, 'output' => $output] = Helpers::createOutputStream();
    $input = new Input($args);

    $exitCode = $command->execute($input, $output);
    $result = Helpers::getOutputContent($stream);

    return ['output' => $result, 'exitCode' => $exitCode];
}

it('registers as queue:work command via #[Command] attribute', function (): void {
    $reflection = new ReflectionClass(WorkCommand::class);
    $attributes = $reflection->getAttributes(Command::class);

    expect($attributes)->toHaveCount(1)
        ->and($attributes[0]->newInstance()->name)->toBe('queue:work');
});

it('implements CommandInterface', function (): void {
    $reflection = new ReflectionClass(WorkCommand::class);

    expect($reflection->implementsInterface(CommandInterface::class))->toBeTrue();
});

it('processes jobs continuously', function (): void {
    $jobsProcessed = 0;
    $maxJobs = 3;

    // Create a worker that processes a few jobs then stops
    $worker = new class ($jobsProcessed, $maxJobs) implements WorkerInterface
    {
        public function __construct(
            private int &$processed,
            private int $max,
        ) {}

        public function work(
            ?string $queue = null,
            bool $once = false,
            int $sleep = 3,
        ): void {
            // Simulate processing multiple jobs
            while ($this->processed < $this->max) {
                $this->processed++;
            }
        }

        public function stop(): void {}
    };

    $command = new WorkCommand($worker);
    ['exitCode' => $exitCode] = executeWorkCommand($command);

    expect($jobsProcessed)->toBe(3)
        ->and($exitCode)->toBe(0);
});

it('supports once flag', function (): void {
    $receivedOnce = null;

    $worker = new class ($receivedOnce) implements WorkerInterface
    {
        public function __construct(
            private ?bool &$once,
        ) {}

        public function work(
            ?string $queue = null,
            bool $once = false,
            int $sleep = 3,
        ): void {
            $this->once = $once;
        }

        public function stop(): void {}
    };

    $command = new WorkCommand($worker);
    executeWorkCommand($command, ['marko', 'queue:work', '--once']);

    expect($receivedOnce)->toBeTrue();
});

it('supports queue option', function (): void {
    $receivedQueue = null;

    $worker = new class ($receivedQueue) implements WorkerInterface
    {
        public function __construct(
            private ?string &$queue,
        ) {}

        public function work(
            ?string $queue = null,
            bool $once = false,
            int $sleep = 3,
        ): void {
            $this->queue = $queue;
        }

        public function stop(): void {}
    };

    $command = new WorkCommand($worker);
    executeWorkCommand($command, ['marko', 'queue:work', '--queue=emails']);

    expect($receivedQueue)->toBe('emails');
});

it('supports sleep option', function (): void {
    $receivedSleep = null;

    $worker = new class ($receivedSleep) implements WorkerInterface
    {
        public function __construct(
            private ?int &$sleep,
        ) {}

        public function work(
            ?string $queue = null,
            bool $once = false,
            int $sleep = 3,
        ): void {
            $this->sleep = $sleep;
        }

        public function stop(): void {}
    };

    $command = new WorkCommand($worker);
    executeWorkCommand($command, ['marko', 'queue:work', '--sleep=5']);

    expect($receivedSleep)->toBe(5);
});

it('displays processing status', function (): void {
    $worker = new class () implements WorkerInterface
    {
        public function work(
            ?string $queue = null,
            bool $once = false,
            int $sleep = 3,
        ): void {}

        public function stop(): void {}
    };

    $command = new WorkCommand($worker);
    ['output' => $output] = executeWorkCommand($command);

    expect($output)->toContain('Processing jobs from queue');
});
