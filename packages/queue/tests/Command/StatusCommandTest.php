<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Queue\Command\StatusCommand;
use Marko\Queue\FailedJob;
use Marko\Queue\FailedJobRepositoryInterface;
use Marko\Queue\JobInterface;
use Marko\Queue\QueueConfig;
use Marko\Queue\QueueInterface;

/**
 * Helper to capture command output for StatusCommand tests.
 *
 * @return array{stream: resource, output: Output}
 */
function createStatusOutputStream(): array
{
    $stream = fopen('php://memory', 'r+');

    return [
        'stream' => $stream,
        'output' => new Output($stream),
    ];
}

/**
 * Helper to get output content from stream for StatusCommand tests.
 *
 * @param resource $stream
 */
function getStatusOutputContent(
    mixed $stream,
): string {
    rewind($stream);

    return stream_get_contents($stream);
}

/**
 * Create a mock ConfigRepositoryInterface for StatusCommand tests.
 *
 * @param array<string, mixed> $values
 */
function createStatusConfigRepository(
    array $values = [],
): ConfigRepositoryInterface {
    return new readonly class ($values) implements ConfigRepositoryInterface
    {
        /**
         * @param array<string, mixed> $values
         */
        public function __construct(
            private array $values,
        ) {}

        public function get(
            string $key,
            mixed $default = null,
            ?string $scope = null,
        ): mixed {
            return $this->values[$key] ?? $default;
        }

        public function has(
            string $key,
            ?string $scope = null,
        ): bool {
            return array_key_exists($key, $this->values);
        }

        public function getString(
            string $key,
            ?string $default = null,
            ?string $scope = null,
        ): string {
            return (string) ($this->values[$key] ?? $default);
        }

        public function getInt(
            string $key,
            ?int $default = null,
            ?string $scope = null,
        ): int {
            return (int) ($this->values[$key] ?? $default);
        }

        public function getBool(
            string $key,
            ?bool $default = null,
            ?string $scope = null,
        ): bool {
            return (bool) ($this->values[$key] ?? $default);
        }

        public function getFloat(
            string $key,
            ?float $default = null,
            ?string $scope = null,
        ): float {
            return (float) ($this->values[$key] ?? $default);
        }

        public function getArray(
            string $key,
            ?array $default = null,
            ?string $scope = null,
        ): array {
            return (array) ($this->values[$key] ?? $default);
        }

        public function all(
            ?string $scope = null,
        ): array {
            return $this->values;
        }

        public function withScope(
            string $scope,
        ): ConfigRepositoryInterface {
            return $this;
        }
    };
}

/**
 * Create a QueueConfig for StatusCommand tests.
 */
function createStatusQueueConfig(
    string $driver = 'database',
    string $queue = 'default',
): QueueConfig {
    return new QueueConfig(createStatusConfigRepository([
        'queue.driver' => $driver,
        'queue.queue' => $queue,
    ]));
}

/**
 * Create a stub QueueInterface for StatusCommand tests.
 */
function createStatusQueue(
    int $size = 0,
): QueueInterface {
    return new readonly class ($size) implements QueueInterface
    {
        public function __construct(
            private int $sizeValue,
        ) {}

        public function push(
            JobInterface $job,
            ?string $queue = null,
        ): string {
            return '';
        }

        public function later(
            int $delay,
            JobInterface $job,
            ?string $queue = null,
        ): string {
            return '';
        }

        public function pop(
            ?string $queue = null,
        ): ?JobInterface {
            return null;
        }

        public function size(
            ?string $queue = null,
        ): int {
            return $this->sizeValue;
        }

        public function clear(
            ?string $queue = null,
        ): int {
            return 0;
        }

        public function delete(
            string $jobId,
        ): bool {
            return false;
        }

        public function release(
            string $jobId,
            int $delay = 0,
        ): bool {
            return false;
        }
    };
}

/**
 * Create a stub FailedJobRepositoryInterface for StatusCommand tests.
 */
function createStatusFailedJobRepository(
    int $count = 0,
): FailedJobRepositoryInterface {
    return new readonly class ($count) implements FailedJobRepositoryInterface
    {
        public function __construct(
            private int $countValue,
        ) {}

        public function store(FailedJob $failedJob): void {}

        public function all(): array
        {
            return [];
        }

        public function find(
            string $id,
        ): ?FailedJob {
            return null;
        }

        public function delete(
            string $id,
        ): bool {
            return false;
        }

        public function clear(): int
        {
            return 0;
        }

        public function count(): int
        {
            return $this->countValue;
        }
    };
}

/**
 * Execute the StatusCommand and return output and exit code.
 *
 * @return array{output: string, exitCode: int}
 */
function executeStatusCommand(
    StatusCommand $command,
): array {
    ['stream' => $stream, 'output' => $output] = createStatusOutputStream();
    $input = new Input(['marko', 'queue:status']);

    $exitCode = $command->execute($input, $output);
    $result = getStatusOutputContent($stream);

    return ['output' => $result, 'exitCode' => $exitCode];
}

it('registers as queue:status command via #[Command] attribute', function (): void {
    $reflection = new ReflectionClass(StatusCommand::class);
    $attributes = $reflection->getAttributes(Command::class);

    expect($attributes)->toHaveCount(1)
        ->and($attributes[0]->newInstance()->name)->toBe('queue:status');
});

it('implements CommandInterface', function (): void {
    $reflection = new ReflectionClass(StatusCommand::class);

    expect($reflection->implementsInterface(CommandInterface::class))->toBeTrue();
});

it('shows driver', function (): void {
    $command = new StatusCommand(
        config: createStatusQueueConfig(),
        queue: createStatusQueue(),
        failedJobRepository: createStatusFailedJobRepository(),
    );

    ['output' => $output] = executeStatusCommand($command);

    expect($output)->toContain('Queue Driver: database');
});

it('shows queue name', function (): void {
    $command = new StatusCommand(
        config: createStatusQueueConfig(queue: 'high-priority'),
        queue: createStatusQueue(),
        failedJobRepository: createStatusFailedJobRepository(),
    );

    ['output' => $output] = executeStatusCommand($command);

    expect($output)->toContain('Queue Name: high-priority');
});

it('shows pending count', function (): void {
    $command = new StatusCommand(
        config: createStatusQueueConfig(),
        queue: createStatusQueue(size: 42),
        failedJobRepository: createStatusFailedJobRepository(),
    );

    ['output' => $output] = executeStatusCommand($command);

    expect($output)->toContain('Pending Jobs: 42');
});

it('shows failed count', function (): void {
    $command = new StatusCommand(
        config: createStatusQueueConfig(),
        queue: createStatusQueue(),
        failedJobRepository: createStatusFailedJobRepository(count: 2),
    );

    ['output' => $output] = executeStatusCommand($command);

    expect($output)->toContain('Failed Jobs: 2');
});
