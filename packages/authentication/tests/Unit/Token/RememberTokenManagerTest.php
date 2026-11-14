<?php

declare(strict_types=1);

use Marko\Authentication\Token\RememberTokenManager;
use Marko\Testing\Fake\FakeClock;

it('generates cryptographically secure tokens', function () {
    $manager = new RememberTokenManager(new FakeClock());

    $token = $manager->generate();

    // Token should be 64 characters (32 bytes hex encoded)
    expect($token)->toBeString()
        ->and(strlen($token))->toBe(64)
        ->and(ctype_xdigit($token))->toBeTrue();
});

it('generates unique tokens each time', function () {
    $manager = new RememberTokenManager(new FakeClock());

    $tokens = [];
    for ($i = 0; $i < 100; $i++) {
        $tokens[] = $manager->generate();
    }

    // All tokens should be unique
    expect(count(array_unique($tokens)))->toBe(100);
});

it('generates unique hex selectors with no cookie separator in them', function () {
    $manager = new RememberTokenManager(new FakeClock());

    $selectors = array_map(fn (): string => $manager->generateSelector(), range(1, 100));
    $isHex = fn (string $selector): bool => strlen($selector) === 32
        && ctype_xdigit($selector);

    expect(count(array_unique($selectors)))->toBe(100)
        ->and(array_all($selectors, $isHex))->toBeTrue();
});

it('hashes token for storage', function () {
    $manager = new RememberTokenManager(new FakeClock());

    $token = $manager->generate();
    $hash = $manager->hash($token);

    // Hash should be SHA-256 (64 hex characters)
    expect($hash)->toBeString()
        ->and(strlen($hash))->toBe(64)
        ->and(ctype_xdigit($hash))->toBeTrue()
        ->and($hash)->not->toBe($token);
});

it('validates token with timing-safe comparison', function () {
    $manager = new RememberTokenManager(new FakeClock());

    $token = $manager->generate();
    $storedHash = $manager->hash($token);

    // Valid token should validate
    expect($manager->validate($token, $storedHash))->toBeTrue();

    // Invalid token should not validate
    $wrongToken = $manager->generate();
    expect($manager->validate($wrongToken, $storedHash))->toBeFalse();
});

it('treats a token as valid until the clock passes its lifetime', function () {
    $clock = new FakeClock('2026-01-01 12:00:00 UTC');
    $manager = new RememberTokenManager($clock, lifetimeMinutes: 60);
    $createdAt = $clock->now();

    expect($manager->isExpired($createdAt))->toBeFalse();

    $clock->travel('+30 minutes');
    expect($manager->isExpired($createdAt))->toBeFalse();

    $clock->travel('+30 minutes');
    expect($manager->isExpired($createdAt))->toBeFalse();
});

it('treats a token as expired one second after its lifetime on the clock', function () {
    $clock = new FakeClock('2026-01-01 12:00:00 UTC');
    $manager = new RememberTokenManager($clock, lifetimeMinutes: 60);
    $createdAt = $clock->now();

    $clock->travel('+60 minutes +1 second');

    expect($manager->isExpired($createdAt))->toBeTrue();
});

it('supports configurable token lifetime', function () {
    $clock = new FakeClock('2026-01-01 12:00:00 UTC');

    // Short lifetime
    $shortManager = new RememberTokenManager($clock, lifetimeMinutes: 5);
    expect($shortManager->isExpired($clock->now()->modify('-6 minutes')))->toBeTrue();

    // Long lifetime
    $longManager = new RememberTokenManager($clock, lifetimeMinutes: 60 * 24 * 7); // 7 days
    expect($longManager->isExpired($clock->now()->modify('-6 days')))->toBeFalse();

    // Default lifetime (30 days)
    $defaultManager = new RememberTokenManager($clock);
    expect($defaultManager->isExpired($clock->now()->modify('-29 days')))->toBeFalse()
        ->and($defaultManager->isExpired($clock->now()->modify('-31 days')))->toBeTrue();
});

it('filters expired tokens against the injected clock', function () {
    $clock = new FakeClock('2026-01-01 12:00:00 UTC');
    $manager = new RememberTokenManager($clock, lifetimeMinutes: 60);
    $now = $clock->now();

    $tokens = [
        ['hash' => 'hash1', 'created_at' => $now->modify('-30 minutes')], // valid
        ['hash' => 'hash2', 'created_at' => $now->modify('-90 minutes')], // expired
        ['hash' => 'hash3', 'created_at' => $now->modify('-5 minutes')],  // valid
        ['hash' => 'hash4', 'created_at' => $now->modify('-2 hours')],    // expired
    ];

    $validTokens = $manager->filterExpired($tokens);

    expect($validTokens)->toHaveCount(2)
        ->and($validTokens[0]['hash'])->toBe('hash1')
        ->and($validTokens[1]['hash'])->toBe('hash3');

    $clock->travel('+31 minutes');

    expect($manager->filterExpired($tokens))->toHaveCount(1);
});

it('computes the expiry of a token issued now from the lifetime', function () {
    $manager = new RememberTokenManager(new FakeClock('2026-01-01 12:00:00'), lifetimeMinutes: 90);

    expect($manager->expiresAt())->toEqual(new DateTimeImmutable('2026-01-01 13:30:00'));
});

it('reports a token expired from its expiry instant onwards', function () {
    $clock = new FakeClock('2026-01-01 12:00:00');
    $manager = new RememberTokenManager($clock);
    $expiresAt = new DateTimeImmutable('2026-01-01 12:10:00');

    expect($manager->hasExpired($expiresAt))->toBeFalse();

    $clock->travel('+10 minutes');

    expect($manager->hasExpired($expiresAt))->toBeTrue();
});

it('rounds the minutes until an expiry up, never below one', function () {
    $manager = new RememberTokenManager(new FakeClock('2026-01-01 12:00:00'));

    expect($manager->minutesUntil(new DateTimeImmutable('2026-01-01 12:30:00')))->toBe(30)
        ->and($manager->minutesUntil(new DateTimeImmutable('2026-01-01 12:00:30')))->toBe(1)
        ->and($manager->minutesUntil(new DateTimeImmutable('2026-01-01 11:00:00')))->toBe(1);
});
