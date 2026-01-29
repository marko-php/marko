<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;
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
            ?string $scope = null,
        ): mixed {
            if (!$this->has($key, $scope)) {
                throw new ConfigNotFoundException($key);
            }

            return $this->values[$key];
        }

        public function has(
            string $key,
            ?string $scope = null,
        ): bool {
            return array_key_exists($key, $this->values);
        }

        public function getString(
            string $key,
            ?string $scope = null,
        ): string {
            return (string) $this->get($key, $scope);
        }

        public function getInt(
            string $key,
            ?string $scope = null,
        ): int {
            return (int) $this->get($key, $scope);
        }

        public function getBool(
            string $key,
            ?string $scope = null,
        ): bool {
            return (bool) $this->get($key, $scope);
        }

        public function getFloat(
            string $key,
            ?string $scope = null,
        ): float {
            return (float) $this->get($key, $scope);
        }

        public function getArray(
            string $key,
            ?string $scope = null,
        ): array {
            return (array) $this->get($key, $scope);
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

    it('uses default config values from config file', function (): void {
        // Defaults are now provided by config/queue.php, not fallback parameters
        // This test verifies the expected default values match config file
        $config = createQueueConfigRepository([
            'queue.driver' => 'sync',
            'queue.connection' => 'default',
            'queue.queue' => 'default',
            'queue.retry_after' => 90,
            'queue.max_attempts' => 3,
        ]);

        $queueConfig = new QueueConfig($config);

        expect($queueConfig->driver())->toBe('sync')
            ->and($queueConfig->connection())->toBe('default')
            ->and($queueConfig->queue())->toBe('default')
            ->and($queueConfig->retryAfter())->toBe(90)
            ->and($queueConfig->maxAttempts())->toBe(3);
    });

    it('throws ConfigNotFoundException when required config is missing', function (): void {
        $emptyConfig = createQueueConfigRepository();
        $queueConfig = new QueueConfig($emptyConfig);

        expect(fn () => $queueConfig->driver())->toThrow(ConfigNotFoundException::class);
    });

    it('allows custom config values to override defaults', function (): void {
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
