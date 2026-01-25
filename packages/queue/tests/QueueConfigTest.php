<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Queue\QueueConfig;

function createQueueConfigRepository(
    array $values = [],
): ConfigRepositoryInterface {
    return new readonly class ($values) implements ConfigRepositoryInterface
    {
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

describe('QueueConfig', function (): void {
    it('loads driver setting', function (): void {
        $config = createQueueConfigRepository([
            'queue.driver' => 'database',
        ]);

        $queueConfig = new QueueConfig($config);

        expect($queueConfig->driver())->toBe('database');
    });

    it('loads connection setting', function (): void {
        $config = createQueueConfigRepository([
            'queue.connection' => 'redis',
        ]);

        $queueConfig = new QueueConfig($config);

        expect($queueConfig->connection())->toBe('redis');
    });

    it('loads queue name setting', function (): void {
        $config = createQueueConfigRepository([
            'queue.queue' => 'high-priority',
        ]);

        $queueConfig = new QueueConfig($config);

        expect($queueConfig->queue())->toBe('high-priority');
    });

    it('loads retry_after setting', function (): void {
        $config = createQueueConfigRepository([
            'queue.retry_after' => 120,
        ]);

        $queueConfig = new QueueConfig($config);

        expect($queueConfig->retryAfter())->toBe(120);
    });

    it('loads max_attempts setting', function (): void {
        $config = createQueueConfigRepository([
            'queue.max_attempts' => 5,
        ]);

        $queueConfig = new QueueConfig($config);

        expect($queueConfig->maxAttempts())->toBe(5);
    });

    it('provides default values', function (): void {
        $config = createQueueConfigRepository();

        $queueConfig = new QueueConfig($config);

        expect($queueConfig->driver())->toBe('sync')
            ->and($queueConfig->connection())->toBe('default')
            ->and($queueConfig->queue())->toBe('default')
            ->and($queueConfig->retryAfter())->toBe(90)
            ->and($queueConfig->maxAttempts())->toBe(3);
    });

    it('QueueConfig uses environment defaults', function (): void {
        // QueueConfig should use sensible defaults when no configuration is provided
        // These defaults are suitable for development environments (sync driver)
        // while production can override via config files

        $emptyConfig = createQueueConfigRepository();
        $queueConfig = new QueueConfig($emptyConfig);

        // Verify defaults are appropriate for development environment
        expect($queueConfig->driver())->toBe('sync')  // sync for immediate execution in dev
            ->and($queueConfig->connection())->toBe('default')  // standard connection name
            ->and($queueConfig->queue())->toBe('default')  // standard queue name
            ->and($queueConfig->retryAfter())->toBe(90)  // 90 seconds reasonable retry window
            ->and($queueConfig->maxAttempts())->toBe(3);  // 3 attempts before giving up

        // Verify that explicit config values override the defaults
        $customConfig = createQueueConfigRepository([
            'queue.driver' => 'database',
            'queue.connection' => 'mysql',
            'queue.queue' => 'high-priority',
            'queue.retry_after' => 300,
            'queue.max_attempts' => 5,
        ]);
        $customQueueConfig = new QueueConfig($customConfig);

        expect($customQueueConfig->driver())->toBe('database')
            ->and($customQueueConfig->connection())->toBe('mysql')
            ->and($customQueueConfig->queue())->toBe('high-priority')
            ->and($customQueueConfig->retryAfter())->toBe(300)
            ->and($customQueueConfig->maxAttempts())->toBe(5);
    });
});
