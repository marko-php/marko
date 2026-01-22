<?php

declare(strict_types=1);

use Marko\Hashing\Contracts\HasherInterface;
use Marko\Hashing\Exceptions\InvalidHasherConfigException;
use Marko\Hashing\Hash\Argon2Hasher;

it('implements HasherInterface', function () {
    $hasher = new Argon2Hasher(memory: 1024, time: 1, threads: 1);

    expect($hasher)->toBeInstanceOf(HasherInterface::class);
});

it('uses default parameters when none provided', function () {
    expect(Argon2Hasher::DEFAULT_MEMORY)->toBe(65536)
        ->and(Argon2Hasher::DEFAULT_TIME)->toBe(4)
        ->and(Argon2Hasher::DEFAULT_THREADS)->toBe(1);
});

it('accepts custom memory parameter', function () {
    $hasher = new Argon2Hasher(memory: 1024, time: 1, threads: 1);

    $hash = $hasher->hash('password');

    expect($hash)->toContain('argon2id');
});

it('accepts custom time parameter', function () {
    $hasher = new Argon2Hasher(memory: 1024, time: 1, threads: 1);

    $hash = $hasher->hash('password');

    expect($hash)->toContain('argon2id');
});

it('accepts custom threads parameter', function () {
    $hasher = new Argon2Hasher(memory: 1024, time: 1, threads: 1);

    $hash = $hasher->hash('password');

    expect($hash)->toContain('argon2id');
});

it('hashes a password with argon2id algorithm', function () {
    $hasher = new Argon2Hasher(memory: 1024, time: 1, threads: 1);

    $hash = $hasher->hash('password');

    expect($hash)->toContain('$argon2id$');
});

it('produces different hashes for the same password due to salt', function () {
    $hasher = new Argon2Hasher(memory: 1024, time: 1, threads: 1);

    $hash1 = $hasher->hash('password');
    $hash2 = $hasher->hash('password');

    expect($hash1)->not->toBe($hash2);
});

it('verifies correct password returns true', function () {
    $hasher = new Argon2Hasher(memory: 1024, time: 1, threads: 1);

    $hash = $hasher->hash('password');

    expect($hasher->verify('password', $hash))->toBeTrue();
});

it('verifies incorrect password returns false', function () {
    $hasher = new Argon2Hasher(memory: 1024, time: 1, threads: 1);

    $hash = $hasher->hash('password');

    expect($hasher->verify('wrong-password', $hash))->toBeFalse();
});

it('returns argon2id as algorithm name', function () {
    $hasher = new Argon2Hasher(memory: 1024, time: 1, threads: 1);

    expect($hasher->algorithm())->toBe('argon2id');
});

it('indicates rehash needed when parameters change', function () {
    $hasher = new Argon2Hasher(memory: 1024, time: 1, threads: 1);
    $hash = $hasher->hash('password');

    $newHasher = new Argon2Hasher(memory: 2048, time: 2, threads: 1);

    expect($newHasher->needsRehash($hash))->toBeTrue();
});

it('indicates no rehash needed when parameters are the same', function () {
    $hasher = new Argon2Hasher(memory: 1024, time: 1, threads: 1);
    $hash = $hasher->hash('password');

    expect($hasher->needsRehash($hash))->toBeFalse();
});

it('throws InvalidHasherConfigException when memory is below 8', function () {
    expect(fn () => new Argon2Hasher(memory: 7))
        ->toThrow(InvalidHasherConfigException::class, 'Invalid Argon2 memory parameter');
});

it('throws InvalidHasherConfigException when time is below 1', function () {
    expect(fn () => new Argon2Hasher(time: 0))
        ->toThrow(InvalidHasherConfigException::class, 'Invalid Argon2 time parameter');
});

it('throws InvalidHasherConfigException when threads is below 1', function () {
    expect(fn () => new Argon2Hasher(threads: 0))
        ->toThrow(InvalidHasherConfigException::class, 'Invalid Argon2 threads parameter');
});

it('provides helpful context in memory validation exception', function () {
    try {
        new Argon2Hasher(memory: 5);
    } catch (InvalidHasherConfigException $e) {
        expect($e->getContext())->toContain('Memory must be >= 8')
            ->and($e->getSuggestion())->toContain('Update config/hashing.php');
    }
});

it('provides helpful context in time validation exception', function () {
    try {
        new Argon2Hasher(time: 0);
    } catch (InvalidHasherConfigException $e) {
        expect($e->getContext())->toContain('Time must be >= 1')
            ->and($e->getSuggestion())->toContain('Update config/hashing.php');
    }
});

it('provides helpful context in threads validation exception', function () {
    try {
        new Argon2Hasher(threads: 0);
    } catch (InvalidHasherConfigException $e) {
        expect($e->getContext())->toContain('Threads must be >= 1')
            ->and($e->getSuggestion())->toContain('Update config/hashing.php');
    }
});

it('accepts minimum valid memory of 8', function () {
    $hasher = new Argon2Hasher(memory: 8);

    $hash = $hasher->hash('password');

    expect($hash)->toContain('argon2id');
});

it('accepts minimum valid time of 1', function () {
    $hasher = new Argon2Hasher(time: 1);

    $hash = $hasher->hash('password');

    expect($hash)->toContain('argon2id');
});

it('accepts minimum valid threads of 1', function () {
    $hasher = new Argon2Hasher(threads: 1);

    $hash = $hasher->hash('password');

    expect($hash)->toContain('argon2id');
});
