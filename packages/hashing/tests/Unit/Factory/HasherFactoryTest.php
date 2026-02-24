<?php

declare(strict_types=1);

use Marko\Hashing\Config\HashConfig;
use Marko\Hashing\Exceptions\HasherNotFoundException;
use Marko\Hashing\Factory\HasherFactory;
use Marko\Hashing\Hash\Argon2Hasher;
use Marko\Hashing\Hash\BcryptHasher;
use Marko\Testing\Fake\FakeConfigRepository;

it('uses FakeConfigRepository in HasherFactoryTest', function (): void {
    $config = new FakeConfigRepository(['hashing.hashers.bcrypt.cost' => 4]);

    expect($config)->toBeInstanceOf(FakeConfigRepository::class);
});

function createFactoryWithConfig(
    array $configData = [],
): HasherFactory {
    return new HasherFactory(new HashConfig(new FakeConfigRepository($configData)));
}

it('creates bcrypt hasher', function (): void {
    $factory = createFactoryWithConfig([
        'hashing.hashers.bcrypt.cost' => 4,
    ]);

    $hasher = $factory->make('bcrypt');

    expect($hasher)->toBeInstanceOf(BcryptHasher::class);
});

it('creates argon2id hasher', function (): void {
    $factory = createFactoryWithConfig([
        'hashing.hashers.argon2id.memory' => 1024,
        'hashing.hashers.argon2id.time' => 1,
        'hashing.hashers.argon2id.threads' => 1,
    ]);

    $hasher = $factory->make('argon2id');

    expect($hasher)->toBeInstanceOf(Argon2Hasher::class);
});

it('creates bcrypt hasher with configured cost', function (): void {
    $factory = createFactoryWithConfig([
        'hashing.hashers.bcrypt.cost' => 4,
    ]);

    $hasher = $factory->make('bcrypt');
    $hash = $hasher->hash('password');

    expect($hash)->toStartWith('$2y$04$');
});

it('creates argon2id hasher with configured parameters', function (): void {
    $factory = createFactoryWithConfig([
        'hashing.hashers.argon2id.memory' => 1024,
        'hashing.hashers.argon2id.time' => 1,
        'hashing.hashers.argon2id.threads' => 1,
    ]);

    $hasher = $factory->make('argon2id');

    expect($hasher)->toBeInstanceOf(Argon2Hasher::class)
        ->and($hasher->hash('password'))->toContain('argon2id');
});

it('throws HasherNotFoundException for unknown hasher name', function (): void {
    $factory = createFactoryWithConfig();

    expect(fn () => $factory->make('sha256'))
        ->toThrow(HasherNotFoundException::class, "Hasher 'sha256' not found");
});

it('provides helpful suggestion in HasherNotFoundException', function (): void {
    $factory = createFactoryWithConfig();

    try {
        $factory->make('md5');
    } catch (HasherNotFoundException $e) {
        expect($e->getSuggestion())->toContain('config/hashing.php');
    }
});

it('creates bcrypt hasher with cost from config', function (): void {
    $factory = createFactoryWithConfig([
        'hashing.hashers.bcrypt.cost' => 12,
    ]);

    $hasher = $factory->make('bcrypt');

    // Verify default cost constant is 12 (without slow hashing)
    expect(BcryptHasher::DEFAULT_COST)->toBe(12)
        ->and($hasher)->toBeInstanceOf(BcryptHasher::class);
});

it('creates argon2 hasher with parameters from config', function (): void {
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
