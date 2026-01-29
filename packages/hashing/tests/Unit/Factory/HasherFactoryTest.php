<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Hashing\Config\HashConfig;
use Marko\Hashing\Exceptions\HasherNotFoundException;
use Marko\Hashing\Factory\HasherFactory;
use Marko\Hashing\Hash\Argon2Hasher;
use Marko\Hashing\Hash\BcryptHasher;

function createFactoryWithConfig(
    array $configData = [],
): HasherFactory {
    $configRepo = new readonly class ($configData) implements ConfigRepositoryInterface
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

    return new HasherFactory(new HashConfig($configRepo));
}

it('creates bcrypt hasher', function () {
    $factory = createFactoryWithConfig([
        'hashing.hashers.bcrypt.cost' => 4,
    ]);

    $hasher = $factory->make('bcrypt');

    expect($hasher)->toBeInstanceOf(BcryptHasher::class);
});

it('creates argon2id hasher', function () {
    $factory = createFactoryWithConfig([
        'hashing.hashers.argon2id.memory' => 1024,
        'hashing.hashers.argon2id.time' => 1,
        'hashing.hashers.argon2id.threads' => 1,
    ]);

    $hasher = $factory->make('argon2id');

    expect($hasher)->toBeInstanceOf(Argon2Hasher::class);
});

it('creates bcrypt hasher with configured cost', function () {
    $factory = createFactoryWithConfig([
        'hashing.hashers.bcrypt.cost' => 4,
    ]);

    $hasher = $factory->make('bcrypt');
    $hash = $hasher->hash('password');

    expect($hash)->toStartWith('$2y$04$');
});

it('creates argon2id hasher with configured parameters', function () {
    $factory = createFactoryWithConfig([
        'hashing.hashers.argon2id.memory' => 1024,
        'hashing.hashers.argon2id.time' => 1,
        'hashing.hashers.argon2id.threads' => 1,
    ]);

    $hasher = $factory->make('argon2id');

    expect($hasher)->toBeInstanceOf(Argon2Hasher::class)
        ->and($hasher->hash('password'))->toContain('argon2id');
});

it('throws HasherNotFoundException for unknown hasher name', function () {
    $factory = createFactoryWithConfig();

    expect(fn () => $factory->make('sha256'))
        ->toThrow(HasherNotFoundException::class, "Hasher 'sha256' not found");
});

it('provides helpful suggestion in HasherNotFoundException', function () {
    $factory = createFactoryWithConfig();

    try {
        $factory->make('md5');
    } catch (HasherNotFoundException $e) {
        expect($e->getSuggestion())->toContain('config/hashing.php');
    }
});

it('creates bcrypt hasher with cost from config', function () {
    $factory = createFactoryWithConfig([
        'hashing.hashers.bcrypt.cost' => 12,
    ]);

    $hasher = $factory->make('bcrypt');

    // Verify default cost constant is 12 (without slow hashing)
    expect(BcryptHasher::DEFAULT_COST)->toBe(12)
        ->and($hasher)->toBeInstanceOf(BcryptHasher::class);
});

it('creates argon2 hasher with parameters from config', function () {
    $factory = createFactoryWithConfig([
        'hashing.hashers.argon2id.memory' => 65536,
        'hashing.hashers.argon2id.time' => 4,
        'hashing.hashers.argon2id.threads' => 1,
    ]);

    $hasher = $factory->make('argon2id');

    // Verify default constants (without slow hashing)
    expect(Argon2Hasher::DEFAULT_MEMORY)->toBe(65536)
        ->and(Argon2Hasher::DEFAULT_TIME)->toBe(4)
        ->and(Argon2Hasher::DEFAULT_THREADS)->toBe(1)
        ->and($hasher)->toBeInstanceOf(Argon2Hasher::class);
});
