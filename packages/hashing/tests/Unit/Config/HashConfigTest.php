<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Hashing\Config\HashConfig;

function createHashConfigRepository(
    array $configData = [],
): ConfigRepositoryInterface {
    return new readonly class ($configData) implements ConfigRepositoryInterface
    {
        public function __construct(
            private array $data,
        ) {}

        public function get(
            string $key,
            ?string $scope = null,
        ): mixed {
            if (!$this->has($key, $scope)) {
                throw new ConfigNotFoundException($key);
            }

            return $this->data[$key];
        }

        public function has(
            string $key,
            ?string $scope = null,
        ): bool {
            return isset($this->data[$key]);
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
            return $this->data;
        }

        public function withScope(
            string $scope,
        ): ConfigRepositoryInterface {
            return $this;
        }
    };
}

it('reads default hasher from config without fallback', function () {
    $config = new HashConfig(createHashConfigRepository([
        'hashing.default' => 'argon2id',
    ]));

    expect($config->defaultHasher())->toBe('argon2id');
});

it('throws exception when default hasher not configured', function () {
    $config = new HashConfig(createHashConfigRepository());

    $config->defaultHasher();
})->throws(ConfigNotFoundException::class);

it('reads bcrypt cost from config without fallback', function () {
    $config = new HashConfig(createHashConfigRepository([
        'hashing.hashers.bcrypt.cost' => 14,
    ]));

    expect($config->getBcryptCost())->toBe(14);
});

it('throws exception when bcrypt cost not configured', function () {
    $config = new HashConfig(createHashConfigRepository());

    $config->getBcryptCost();
})->throws(ConfigNotFoundException::class);

it('reads argon2id memory from config without fallback', function () {
    $config = new HashConfig(createHashConfigRepository([
        'hashing.hashers.argon2id.memory' => 131072,
    ]));

    expect($config->getArgon2Memory())->toBe(131072);
});

it('throws exception when argon2id memory not configured', function () {
    $config = new HashConfig(createHashConfigRepository());

    $config->getArgon2Memory();
})->throws(ConfigNotFoundException::class);

it('reads argon2id time from config without fallback', function () {
    $config = new HashConfig(createHashConfigRepository([
        'hashing.hashers.argon2id.time' => 8,
    ]));

    expect($config->getArgon2Time())->toBe(8);
});

it('throws exception when argon2id time not configured', function () {
    $config = new HashConfig(createHashConfigRepository());

    $config->getArgon2Time();
})->throws(ConfigNotFoundException::class);

it('reads argon2id threads from config without fallback', function () {
    $config = new HashConfig(createHashConfigRepository([
        'hashing.hashers.argon2id.threads' => 4,
    ]));

    expect($config->getArgon2Threads())->toBe(4);
});

it('throws exception when argon2id threads not configured', function () {
    $config = new HashConfig(createHashConfigRepository());

    $config->getArgon2Threads();
})->throws(ConfigNotFoundException::class);

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

it('throws exception when hasher config not found', function () {
    $config = new HashConfig(createHashConfigRepository());

    $config->getHasherConfig('unknown');
})->throws(ConfigNotFoundException::class);

describe('config file', function () {
    it('contains all required keys with defaults', function () {
        $configPath = dirname(__DIR__, 3) . '/config/hashing.php';
        $config = require $configPath;

        expect($config)->toHaveKey('default')
            ->and($config)->toHaveKey('hashers')
            ->and($config['hashers'])->toHaveKey('bcrypt')
            ->and($config['hashers']['bcrypt'])->toHaveKey('cost')
            ->and($config['hashers'])->toHaveKey('argon2id')
            ->and($config['hashers']['argon2id'])->toHaveKey('memory')
            ->and($config['hashers']['argon2id'])->toHaveKey('time')
            ->and($config['hashers']['argon2id'])->toHaveKey('threads');
    });
});
