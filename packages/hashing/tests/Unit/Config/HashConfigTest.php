<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Hashing\Config\HashConfig;

function createHashConfigRepository(
    array $configData = [],
): ConfigRepositoryInterface {
    return new class ($configData) implements ConfigRepositoryInterface
    {
        public function __construct(
            private readonly array $data,
        ) {}

        public function get(
            string $key,
            mixed $default = null,
            ?string $scope = null,
        ): mixed {
            return $this->data[$key] ?? $default;
        }

        public function has(
            string $key,
            ?string $scope = null,
        ): bool {
            return isset($this->data[$key]);
        }

        public function getString(
            string $key,
            ?string $default = null,
            ?string $scope = null,
        ): string {
            return (string) ($this->data[$key] ?? $default);
        }

        public function getInt(
            string $key,
            ?int $default = null,
            ?string $scope = null,
        ): int {
            return (int) ($this->data[$key] ?? $default);
        }

        public function getBool(
            string $key,
            ?bool $default = null,
            ?string $scope = null,
        ): bool {
            return (bool) ($this->data[$key] ?? $default);
        }

        public function getFloat(
            string $key,
            ?float $default = null,
            ?string $scope = null,
        ): float {
            return (float) ($this->data[$key] ?? $default);
        }

        public function getArray(
            string $key,
            ?array $default = null,
            ?string $scope = null,
        ): array {
            return (array) ($this->data[$key] ?? $default ?? []);
        }

        public function all(
            ?string $scope = null,
        ): array {
            return $this->data;
        }

        public function withScope(
            string $scope,
        ): ConfigRepositoryInterface {
            return $this;
        }
    };
}

it('returns configured default hasher', function () {
    $config = new HashConfig(createHashConfigRepository([
        'hashing.default' => 'argon2id',
    ]));

    expect($config->defaultHasher())->toBe('argon2id');
});

it('returns bcrypt as default when not configured', function () {
    $config = new HashConfig(createHashConfigRepository());

    expect($config->defaultHasher())->toBe('bcrypt');
});

it('returns true when hasher is configured', function () {
    $config = new HashConfig(createHashConfigRepository([
        'hashing.hashers.bcrypt' => ['cost' => 12],
    ]));

    expect($config->hasHasher('bcrypt'))->toBeTrue();
});

it('returns false when hasher is not configured', function () {
    $config = new HashConfig(createHashConfigRepository());

    expect($config->hasHasher('unknown'))->toBeFalse();
});

it('returns hasher config array', function () {
    $config = new HashConfig(createHashConfigRepository([
        'hashing.hashers.bcrypt' => ['cost' => 14],
    ]));

    expect($config->getHasherConfig('bcrypt'))->toBe(['cost' => 14]);
});

it('returns empty array for unconfigured hasher', function () {
    $config = new HashConfig(createHashConfigRepository());

    expect($config->getHasherConfig('unknown'))->toBe([]);
});

it('returns configured bcrypt cost', function () {
    $config = new HashConfig(createHashConfigRepository([
        'hashing.hashers.bcrypt.cost' => 14,
    ]));

    expect($config->getBcryptCost())->toBe(14);
});

it('returns default bcrypt cost of 12', function () {
    $config = new HashConfig(createHashConfigRepository());

    expect($config->getBcryptCost())->toBe(12);
});

it('returns configured argon2 memory', function () {
    $config = new HashConfig(createHashConfigRepository([
        'hashing.hashers.argon2id.memory' => 131072,
    ]));

    expect($config->getArgon2Memory())->toBe(131072);
});

it('returns default argon2 memory of 65536', function () {
    $config = new HashConfig(createHashConfigRepository());

    expect($config->getArgon2Memory())->toBe(65536);
});

it('returns configured argon2 time', function () {
    $config = new HashConfig(createHashConfigRepository([
        'hashing.hashers.argon2id.time' => 8,
    ]));

    expect($config->getArgon2Time())->toBe(8);
});

it('returns default argon2 time of 4', function () {
    $config = new HashConfig(createHashConfigRepository());

    expect($config->getArgon2Time())->toBe(4);
});

it('returns configured argon2 threads', function () {
    $config = new HashConfig(createHashConfigRepository([
        'hashing.hashers.argon2id.threads' => 4,
    ]));

    expect($config->getArgon2Threads())->toBe(4);
});

it('returns default argon2 threads of 1', function () {
    $config = new HashConfig(createHashConfigRepository());

    expect($config->getArgon2Threads())->toBe(1);
});
