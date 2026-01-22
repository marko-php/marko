<?php

declare(strict_types=1);

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Queue\Command\QueueClearCommand;
use Marko\Queue\JobInterface;
use Marko\Queue\QueueInterface;

/**
 * Create a stub Queue for testing.
 */
function createQueueStub(
    int $clearReturn = 0,
    ?string $expectedQueue = null,
): QueueInterface {
    return new class ($clearReturn, $expectedQueue) implements QueueInterface
    {
        private int $clearCallCount = 0;

        private ?string $lastClearedQueue = null;

        public function __construct(
            private readonly int $clearReturn,
            private readonly ?string $expectedQueue,
        ) {}

        public function push(
            JobInterface $job,
            ?string $queue = null,
        ): string {
            return 'job-id';
        }

        public function later(
            int $delay,
            JobInterface $job,
            ?string $queue = null,
        ): string {
            return 'job-id';
        }

        public function pop(
            ?string $queue = null,
        ): ?JobInterface {
            return null;
        }

        public function size(
            ?string $queue = null,
        ): int {
            return 0;
        }

        public function clear(
            ?string $queue = null,
        ): int {
            $this->clearCallCount++;
            $this->lastClearedQueue = $queue;

            return $this->clearReturn;
        }

        public function delete(
            string $jobId,
        ): bool {
            return true;
        }

        public function release(
            string $jobId,
            int $delay = 0,
        ): bool {
            return true;
        }

        public function getClearCallCount(): int
        {
            return $this->clearCallCount;
        }

        public function getLastClearedQueue(): ?string
        {
            return $this->lastClearedQueue;
        }
    };
}

/**
 * Execute the queue:clear command and return output.
 *
 * @param array<int, string> $argv
 *
 * @return array{output: string, exitCode: int, queue: object}
 */
function executeQueueClear(
    array $argv,
    int $clearReturn = 0,
): array {
    $queue = createQueueStub($clearReturn);
    $command = new QueueClearCommand($queue);

    $stream = fopen('php://memory', 'r+');
    $input = new Input($argv);
    $output = new Output($stream);

    $exitCode = $command->execute($input, $output);

    rewind($stream);
    $result = stream_get_contents($stream);

    return ['output' => $result, 'exitCode' => $exitCode, 'queue' => $queue];
}

it('has Command attribute with name queue:clear', function (): void {
    $reflection = new ReflectionClass(QueueClearCommand::class);
    $attributes = $reflection->getAttributes(Command::class);

    expect($attributes)->toHaveCount(1);

    $command = $attributes[0]->newInstance();

    expect($command->name)->toBe('queue:clear');
});

it('has Command attribute with description Clear all jobs from queue', function (): void {
    $reflection = new ReflectionClass(QueueClearCommand::class);
    $attributes = $reflection->getAttributes(Command::class);

    $command = $attributes[0]->newInstance();

    expect($command->description)->toBe('Clear all jobs from queue');
});

it('implements CommandInterface', function (): void {
    $reflection = new ReflectionClass(QueueClearCommand::class);

    expect($reflection->implementsInterface(CommandInterface::class))->toBeTrue();
});

it('clears all jobs from queue', function (): void {
    ['exitCode' => $exitCode, 'queue' => $queue] = executeQueueClear(
        ['marko', 'queue:clear'],
        15,
    );

    expect($exitCode)->toBe(0)
        ->and($queue->getClearCallCount())->toBe(1)
        ->and($queue->getLastClearedQueue())->toBeNull();
});

it('supports queue option', function (): void {
    ['exitCode' => $exitCode, 'queue' => $queue] = executeQueueClear(
        ['marko', 'queue:clear', '--queue=emails'],
        3,
    );

    expect($exitCode)->toBe(0)
        ->and($queue->getClearCallCount())->toBe(1)
        ->and($queue->getLastClearedQueue())->toBe('emails');
});

it('shows cleared count for default queue', function (): void {
    ['output' => $output] = executeQueueClear(
        ['marko', 'queue:clear'],
        15,
    );

    expect($output)->toContain('Cleared 15 jobs from queue.');
});

it('shows cleared count for specific queue', function (): void {
    ['output' => $output] = executeQueueClear(
        ['marko', 'queue:clear', '--queue=emails'],
        3,
    );

    expect($output)->toContain("Cleared 3 jobs from 'emails' queue.");
});
