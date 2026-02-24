<?php

declare(strict_types=1);

use Marko\Cache\Contracts\CacheInterface;
use Marko\Cache\Contracts\CacheItemInterface;
use Marko\Health\Checks\CacheHealthCheck;
use Marko\Health\Value\HealthStatus;

it('checks cache read/write via CacheHealthCheck', function () {
    $store = [];
    $cache = new class ($store) implements CacheInterface
    {
        public function __construct(private array &$store) {}

        public function get(string $key, mixed $default = null): mixed
        {
            return $this->store[$key] ?? $default;
        }

        public function set(string $key, mixed $value, ?int $ttl = null): bool
        {
            $this->store[$key] = $value;

            return true;
        }

        public function has(string $key): bool
        {
            return isset($this->store[$key]);
        }

        public function delete(string $key): bool
        {
            unset($this->store[$key]);

            return true;
        }

        public function clear(): bool
        {
            $this->store = [];

            return true;
        }

        public function getItem(string $key): CacheItemInterface
        {
            throw new RuntimeException('Not implemented');
        }

        public function getMultiple(array $keys, mixed $default = null): iterable
        {
            return [];
        }

        public function setMultiple(array $values, ?int $ttl = null): bool
        {
            return true;
        }

        public function deleteMultiple(array $keys): bool
        {
            return true;
        }
    };

    $check = new CacheHealthCheck($cache);

    expect($check->getName())->toBe('cache');

    $result = $check->check();

    expect($result->status)->toBe(HealthStatus::Healthy)
        ->and($result->name)->toBe('cache');
});

it('returns unhealthy status when cache write fails', function () {
    $cache = new class () implements CacheInterface
    {
        public function get(string $key, mixed $default = null): mixed
        {
            return null;
        }

        public function set(string $key, mixed $value, ?int $ttl = null): bool
        {
            throw new RuntimeException('Cache unavailable');
        }

        public function has(string $key): bool
        {
            return false;
        }

        public function delete(string $key): bool
        {
            return false;
        }

        public function clear(): bool
        {
            return false;
        }

        public function getItem(string $key): CacheItemInterface
        {
            throw new RuntimeException('Not implemented');
        }

        public function getMultiple(array $keys, mixed $default = null): iterable
        {
            return [];
        }

        public function setMultiple(array $values, ?int $ttl = null): bool
        {
            return false;
        }

        public function deleteMultiple(array $keys): bool
        {
            return false;
        }
    };

    $check = new CacheHealthCheck($cache);
    $result = $check->check();

    expect($result->status)->toBe(HealthStatus::Unhealthy)
        ->and($result->message)->toContain('Cache unavailable');
});
