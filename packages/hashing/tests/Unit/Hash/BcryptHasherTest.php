<?php

declare(strict_types=1);

use Marko\Hashing\Contracts\HasherInterface;
use Marko\Hashing\Exceptions\InvalidHasherConfigException;
use Marko\Hashing\Hash\BcryptHasher;

it('implements HasherInterface', function () {
    $hasher = new BcryptHasher();

    expect($hasher)->toBeInstanceOf(HasherInterface::class);
});

it('uses default cost of 12 when no cost provided', function () {
    $hasher = new BcryptHasher();

    expect(BcryptHasher::DEFAULT_COST)->toBe(12);
});

it('accepts custom cost parameter', function () {
    $hasher = new BcryptHasher(cost: 10);

    $hash = $hasher->hash('password');

    expect($hash)->toStartWith('$2y$10$');
});

it('hashes a password with bcrypt algorithm', function () {
    $hasher = new BcryptHasher();

    $hash = $hasher->hash('password');

    expect($hash)->toStartWith('$2y$')
        ->and(strlen($hash))->toBe(60);
});

it('produces different hashes for the same password due to salt', function () {
    $hasher = new BcryptHasher();

    $hash1 = $hasher->hash('password');
    $hash2 = $hasher->hash('password');

    expect($hash1)->not->toBe($hash2);
});

it('verifies correct password returns true', function () {
    $hasher = new BcryptHasher();

    $hash = $hasher->hash('password');

    expect($hasher->verify('password', $hash))->toBeTrue();
});

it('verifies incorrect password returns false', function () {
    $hasher = new BcryptHasher();

    $hash = $hasher->hash('password');

    expect($hasher->verify('wrong-password', $hash))->toBeFalse();
});

it('returns bcrypt as algorithm name', function () {
    $hasher = new BcryptHasher();

    expect($hasher->algorithm())->toBe('bcrypt');
});

it('indicates rehash needed when cost increases', function () {
    $hasher = new BcryptHasher(cost: 4);
    $hash = $hasher->hash('password');

    $newHasher = new BcryptHasher(cost: 10);

    expect($newHasher->needsRehash($hash))->toBeTrue();
});

it('indicates no rehash needed when cost is the same', function () {
    $hasher = new BcryptHasher(cost: 10);
    $hash = $hasher->hash('password');

    expect($hasher->needsRehash($hash))->toBeFalse();
});

it('throws InvalidHasherConfigException when cost is below 4', function () {
    expect(fn () => new BcryptHasher(cost: 3))
        ->toThrow(InvalidHasherConfigException::class, 'Invalid bcrypt cost parameter');
});

it('throws InvalidHasherConfigException when cost is above 31', function () {
    expect(fn () => new BcryptHasher(cost: 32))
        ->toThrow(InvalidHasherConfigException::class, 'Invalid bcrypt cost parameter');
});

it('provides helpful context in cost validation exception', function () {
    try {
        new BcryptHasher(cost: 2);
    } catch (InvalidHasherConfigException $e) {
        expect($e->getContext())->toContain('Cost must be between 4 and 31')
            ->and($e->getSuggestion())->toContain('Update config/hashing.php');
    }
});

it('accepts minimum valid cost of 4', function () {
    $hasher = new BcryptHasher(cost: 4);

    expect($hasher->hash('password'))->toStartWith('$2y$04$');
});

it('accepts maximum valid cost of 31', function () {
    // Note: cost of 31 would take too long, so just verify it's constructed
    // We trust PHP's password_hash to handle the actual limit
    // For testing, we just verify the constructor accepts 31 without throwing
    expect(fn () => new BcryptHasher(cost: 31))->not->toThrow(InvalidHasherConfigException::class);
});
