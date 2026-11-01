<?php

declare(strict_types=1);

use Marko\Authentication\Config\AuthConfig;
use Marko\Authentication\Contracts\PasswordResetTokenRepositoryInterface;
use Marko\Authentication\Exceptions\AuthException;
use Marko\Authentication\PasswordReset\CachePasswordResetTokenRepository;
use Marko\Cache\CacheOperations;
use Marko\Cache\Config\CacheConfig;
use Marko\Cache\Memory\Driver\ArrayCacheDriver;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;

/**
 * @return array{CachePasswordResetTokenRepository, FakeClock, ArrayCacheDriver}
 */
function cacheResetTokenRepository(): array
{
    $clock = new FakeClock('2026-06-01 12:00:00');
    $cache = new ArrayCacheDriver(new CacheConfig(new FakeConfigRepository([
        'cache.path' => sys_get_temp_dir(),
        'cache.default_ttl' => 3600,
        'cache.driver' => 'array',
    ])), $clock, new CacheOperations());
    $authConfig = new AuthConfig(new FakeConfigRepository([
        'authentication.password_reset.expire_seconds' => 600,
    ]));

    return [new CachePasswordResetTokenRepository($cache, $authConfig), $clock, $cache];
}

it('implements PasswordResetTokenRepositoryInterface', function (): void {
    [$repository] = cacheResetTokenRepository();

    expect($repository)->toBeInstanceOf(PasswordResetTokenRepositoryInterface::class);
});

it('stores and finds a token hash with its creation time', function (): void {
    [$repository, $clock] = cacheResetTokenRepository();

    $repository->store(7, 'hash-7', $clock->now());
    $record = $repository->find(7);

    expect($record)->not->toBeNull()
        ->and($record->tokenHash)->toBe('hash-7')
        ->and($record->createdAt->getTimestamp())->toBe($clock->now()->getTimestamp());
});

it('returns null when no token is stored for the user', function (): void {
    [$repository] = cacheResetTokenRepository();

    expect($repository->find(7))->toBeNull();
});

it('replaces an existing token for the same user', function (): void {
    [$repository, $clock] = cacheResetTokenRepository();

    $repository->store(7, 'first', $clock->now());
    $repository->store(7, 'second', $clock->now());

    expect($repository->find(7)->tokenHash)->toBe('second');
});

it('keeps tokens of different users apart, including string identifiers', function (): void {
    [$repository, $clock] = cacheResetTokenRepository();

    $repository->store(7, 'hash-7', $clock->now());
    $repository->store('user:{uuid}/8', 'hash-8', $clock->now());

    expect($repository->find(7)->tokenHash)->toBe('hash-7')
        ->and($repository->find('user:{uuid}/8')->tokenHash)->toBe('hash-8');
});

it('deletes the token for a user', function (): void {
    [$repository, $clock] = cacheResetTokenRepository();
    $repository->store(7, 'hash-7', $clock->now());

    $repository->delete(7);

    expect($repository->find(7))->toBeNull();
});

it('lets the cache drop a token once expire_seconds have passed', function (): void {
    [$repository, $clock] = cacheResetTokenRepository();
    $repository->store(7, 'hash-7', $clock->now());

    $clock->travel('+601 seconds');

    expect($repository->find(7))->toBeNull();
});

it('reports zero from deleteExpired because the cache expires tokens itself', function (): void {
    [$repository, $clock] = cacheResetTokenRepository();
    $repository->store(7, 'hash-7', $clock->now());

    expect($repository->deleteExpired($clock->now()->modify('+1 day')))->toBe(0);
});

it('throws when the cached record is not a token record', function (): void {
    [$repository, , $cache] = cacheResetTokenRepository();
    $repository->store(7, 'hash-7', new DateTimeImmutable());
    $cache->set('auth.password_reset.' . hash('sha256', '7'), 'garbage');

    expect(fn () => $repository->find(7))->toThrow(AuthException::class, 'password reset token');
});
