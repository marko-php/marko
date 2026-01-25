<?php

declare(strict_types=1);

namespace Marko\Queue\Sync\Tests\Unit;

use Marko\Config\ConfigRepositoryInterface;
use Marko\Queue\QueueConfig;
use Marko\Queue\QueueInterface;
use Marko\Queue\Sync\Factory\SyncQueueFactory;
use Marko\Queue\Sync\SyncQueue;

it('SyncQueueFactory creates configured queue', function (): void {
    $config = createQueueConfigMock();

    $factory = new SyncQueueFactory($config);
    $queue = $factory->create();

    expect($queue)->toBeInstanceOf(QueueInterface::class)
        ->and($queue)->toBeInstanceOf(SyncQueue::class);
});

function createQueueConfigMock(
    string $driver = 'sync',
    string $queue = 'default',
): QueueConfig {
    $repository = new readonly class ($driver, $queue) implements ConfigRepositoryInterface
    {
        public function __construct(
            private string $driver,
            private string $queue,
        ) {}

        public function get(
            string $key,
            mixed $default = null,
            ?string $scope = null,
        ): mixed {
            return match ($key) {
                'queue.driver' => $this->driver,
                'queue.queue' => $this->queue,
                'queue.connection' => 'default',
                'queue.retry_after' => 90,
                'queue.max_attempts' => 3,
                default => $default,
            };
        }

        public function has(
            string $key,
            ?string $scope = null,
        ): bool {
            return in_array($key, [
                'queue.driver',
                'queue.queue',
                'queue.connection',
                'queue.retry_after',
                'queue.max_attempts',
            ], true);
        }

        public function getString(
            string $key,
            ?string $default = null,
            ?string $scope = null,
        ): string {
            return (string) $this->get($key, $default);
        }

        public function getInt(
            string $key,
            ?int $default = null,
            ?string $scope = null,
        ): int {
            return (int) $this->get($key, $default);
        }

        public function getBool(
            string $key,
            ?bool $default = null,
            ?string $scope = null,
        ): bool {
            return (bool) $this->get($key, $default);
        }

        public function getFloat(
            string $key,
            ?float $default = null,
            ?string $scope = null,
        ): float {
            return (float) $this->get($key, $default);
        }

        public function getArray(
            string $key,
            ?array $default = null,
            ?string $scope = null,
        ): array {
            return (array) ($this->get($key) ?? $default ?? []);
        }

        public function all(
            ?string $scope = null,
        ): array {
            return [
                'queue.driver' => $this->driver,
                'queue.queue' => $this->queue,
                'queue.connection' => 'default',
                'queue.retry_after' => 90,
                'queue.max_attempts' => 3,
            ];
        }

        public function withScope(
            string $scope,
        ): ConfigRepositoryInterface {
            return $this;
        }
    };

    return new QueueConfig($repository);
}
