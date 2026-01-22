<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Queue\QueueConfig;

function createMockConfigRepository(
    array $values = [],
): ConfigRepositoryInterface {
    return new class ($values) implements ConfigRepositoryInterface
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
        $config = createMockConfigRepository([
            'queue.driver' => 'database',
        ]);

        $queueConfig = new QueueConfig($config);

        expect($queueConfig->driver())->toBe('database');
    });

    it('loads connection setting', function (): void {
        $config = createMockConfigRepository([
            'queue.connection' => 'redis',
        ]);

        $queueConfig = new QueueConfig($config);

        expect($queueConfig->connection())->toBe('redis');
    });

    it('loads queue name setting', function (): void {
        $config = createMockConfigRepository([
            'queue.queue' => 'high-priority',
        ]);

        $queueConfig = new QueueConfig($config);

        expect($queueConfig->queue())->toBe('high-priority');
    });

    it('loads retry_after setting', function (): void {
        $config = createMockConfigRepository([
            'queue.retry_after' => 120,
        ]);

        $queueConfig = new QueueConfig($config);

        expect($queueConfig->retryAfter())->toBe(120);
    });

    it('loads max_attempts setting', function (): void {
        $config = createMockConfigRepository([
            'queue.max_attempts' => 5,
        ]);

        $queueConfig = new QueueConfig($config);

        expect($queueConfig->maxAttempts())->toBe(5);
    });

    it('provides default values', function (): void {
        $config = createMockConfigRepository([]);

        $queueConfig = new QueueConfig($config);

        expect($queueConfig->driver())->toBe('sync')
            ->and($queueConfig->connection())->toBe('default')
            ->and($queueConfig->queue())->toBe('default')
            ->and($queueConfig->retryAfter())->toBe(90)
            ->and($queueConfig->maxAttempts())->toBe(3);
    });
});
