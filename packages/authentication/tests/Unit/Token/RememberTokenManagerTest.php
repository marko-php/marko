<?php

declare(strict_types=1);

use Marko\Authentication\Token\RememberTokenManager;

it('generates cryptographically secure tokens', function () {
    $manager = new RememberTokenManager();

    $token = $manager->generate();

    // Token should be 64 characters (32 bytes hex encoded)
    expect($token)->toBeString()
        ->and(strlen($token))->toBe(64)
        ->and(ctype_xdigit($token))->toBeTrue();
});

it('generates unique tokens each time', function () {
    $manager = new RememberTokenManager();

    $tokens = [];
    for ($i = 0; $i < 100; $i++) {
        $tokens[] = $manager->generate();
    }

    // All tokens should be unique
    expect(count(array_unique($tokens)))->toBe(100);
});

it('hashes token for storage', function () {
    $manager = new RememberTokenManager();

    $token = $manager->generate();
    $hash = $manager->hash($token);

    // Hash should be SHA-256 (64 hex characters)
    expect($hash)->toBeString()
        ->and(strlen($hash))->toBe(64)
        ->and(ctype_xdigit($hash))->toBeTrue()
        ->and($hash)->not->toBe($token);
});

it('validates token with timing-safe comparison', function () {
    $manager = new RememberTokenManager();

    $token = $manager->generate();
    $storedHash = $manager->hash($token);

    // Valid token should validate
    expect($manager->validate($token, $storedHash))->toBeTrue();

    // Invalid token should not validate
    $wrongToken = $manager->generate();
    expect($manager->validate($wrongToken, $storedHash))->toBeFalse();
});

it('checks token expiration', function () {
    $manager = new RememberTokenManager(lifetimeMinutes: 60);

    // Token created now should not be expired
    $createdAt = new DateTimeImmutable();
    expect($manager->isExpired($createdAt))->toBeFalse();

    // Token created 30 minutes ago should not be expired
    $createdAt = new DateTimeImmutable('-30 minutes');
    expect($manager->isExpired($createdAt))->toBeFalse();
});

it('returns false for expired tokens', function () {
    $manager = new RememberTokenManager(lifetimeMinutes: 60);

    // Token created 61 minutes ago should be expired
    $createdAt = new DateTimeImmutable('-61 minutes');
    expect($manager->isExpired($createdAt))->toBeTrue();

    // Token created 2 hours ago should be expired
    $createdAt = new DateTimeImmutable('-2 hours');
    expect($manager->isExpired($createdAt))->toBeTrue();
});

it('supports configurable token lifetime', function () {
    // Short lifetime
    $shortManager = new RememberTokenManager(lifetimeMinutes: 5);
    $createdAt = new DateTimeImmutable('-6 minutes');
    expect($shortManager->isExpired($createdAt))->toBeTrue();

    // Long lifetime
    $longManager = new RememberTokenManager(lifetimeMinutes: 60 * 24 * 7); // 7 days
    $createdAt = new DateTimeImmutable('-6 days');
    expect($longManager->isExpired($createdAt))->toBeFalse();

    // Default lifetime (30 days)
    $defaultManager = new RememberTokenManager();
    $createdAt = new DateTimeImmutable('-29 days');
    expect($defaultManager->isExpired($createdAt))->toBeFalse();
});

it('clears expired tokens', function () {
    $manager = new RememberTokenManager(lifetimeMinutes: 60);

    $tokens = [
        ['hash' => 'hash1', 'created_at' => new DateTimeImmutable('-30 minutes')], // valid
        ['hash' => 'hash2', 'created_at' => new DateTimeImmutable('-90 minutes')], // expired
        ['hash' => 'hash3', 'created_at' => new DateTimeImmutable('-5 minutes')],  // valid
        ['hash' => 'hash4', 'created_at' => new DateTimeImmutable('-2 hours')],    // expired
    ];

    $validTokens = $manager->filterExpired($tokens);

    expect($validTokens)->toHaveCount(2)
        ->and($validTokens[0]['hash'])->toBe('hash1')
        ->and($validTokens[1]['hash'])->toBe('hash3');
});
