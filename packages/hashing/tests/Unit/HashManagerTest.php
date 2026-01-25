<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Hashing\Config\HashConfig;
use Marko\Hashing\Contracts\HasherInterface;
use Marko\Hashing\Exceptions\HasherNotFoundException;
use Marko\Hashing\Factory\HasherFactory;
use Marko\Hashing\Hash\Argon2Hasher;
use Marko\Hashing\Hash\BcryptHasher;
use Marko\Hashing\HashManager;

function createHashManager(
    string $defaultHasher = 'bcrypt',
    int $bcryptCost = 4,
    int $argon2Memory = 1024,
    int $argon2Time = 1,
    int $argon2Threads = 1,
): HashManager {
    $configData = [
        'hashing.default' => $defaultHasher,
        'hashing.hashers.bcrypt.cost' => $bcryptCost,
        'hashing.hashers.argon2id.memory' => $argon2Memory,
        'hashing.hashers.argon2id.time' => $argon2Time,
        'hashing.hashers.argon2id.threads' => $argon2Threads,
    ];

    $configRepo = new readonly class ($configData) implements ConfigRepositoryInterface
    {
        public function __construct(
            private array $data,
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

    $hashConfig = new HashConfig($configRepo);
    $factory = new HasherFactory($hashConfig);

    return new HashManager($hashConfig, $factory);
}

it('hashes a password using default hasher', function () {
    $manager = createHashManager();

    $hash = $manager->hash('password');

    expect($hash)->toStartWith('$2y$');
});

it('verifies a password using default hasher', function () {
    $manager = createHashManager();

    $hash = $manager->hash('password');

    expect($manager->verify('password', $hash))->toBeTrue()
        ->and($manager->verify('wrong', $hash))->toBeFalse();
});

it('checks if rehash is needed using default hasher', function () {
    $manager = createHashManager();
    $hash = $manager->hash('password');

    $newManager = createHashManager(bcryptCost: 6);

    expect($newManager->needsRehash($hash))->toBeTrue();
});

it('returns default hasher when no name provided', function () {
    $manager = createHashManager();

    $hasher = $manager->hasher();

    expect($hasher)->toBeInstanceOf(BcryptHasher::class);
});

it('returns specific hasher by name', function () {
    $manager = createHashManager();

    $bcrypt = $manager->hasher('bcrypt');
    $argon2 = $manager->hasher('argon2id');

    expect($bcrypt)->toBeInstanceOf(BcryptHasher::class)
        ->and($argon2)->toBeInstanceOf(Argon2Hasher::class);
});

it('caches hasher instances', function () {
    $manager = createHashManager();

    $hasher1 = $manager->hasher('bcrypt');
    $hasher2 = $manager->hasher('bcrypt');

    expect($hasher1)->toBe($hasher2);
});

it('returns true when hasher exists', function () {
    $manager = createHashManager();

    expect($manager->has('bcrypt'))->toBeTrue()
        ->and($manager->has('argon2id'))->toBeTrue();
});

it('returns false when hasher does not exist', function () {
    $manager = createHashManager();

    expect($manager->has('md5'))->toBeFalse()
        ->and($manager->has('sha256'))->toBeFalse();
});

it('throws HasherNotFoundException for unknown hasher', function () {
    $manager = createHashManager();

    expect(fn () => $manager->hasher('unknown'))
        ->toThrow(HasherNotFoundException::class, "Hasher 'unknown' not found");
});

it('uses argon2id as default when configured', function () {
    $manager = createHashManager(defaultHasher: 'argon2id');

    $hasher = $manager->hasher();

    expect($hasher)->toBeInstanceOf(Argon2Hasher::class)
        ->and($hasher)->toBeInstanceOf(HasherInterface::class);
});

it('hashes with argon2id when configured as default', function () {
    $manager = createHashManager(defaultHasher: 'argon2id');

    $hash = $manager->hash('password');

    expect($hash)->toContain('argon2id');
});

it('can switch between hashers', function () {
    $manager = createHashManager();

    $bcryptHash = $manager->hasher('bcrypt')->hash('password');
    $argon2Hash = $manager->hasher('argon2id')->hash('password');

    expect($bcryptHash)->toStartWith('$2y$')
        ->and($argon2Hash)->toContain('argon2id');
});

it('can verify hashes created by specific hashers', function () {
    $manager = createHashManager();

    $bcryptHash = $manager->hasher('bcrypt')->hash('password');
    $argon2Hash = $manager->hasher('argon2id')->hash('password');

    expect($manager->hasher('bcrypt')->verify('password', $bcryptHash))->toBeTrue()
        ->and($manager->hasher('argon2id')->verify('password', $argon2Hash))->toBeTrue();
});
