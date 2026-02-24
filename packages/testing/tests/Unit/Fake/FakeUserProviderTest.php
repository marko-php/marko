<?php

declare(strict_types=1);

use Marko\Authentication\Contracts\UserProviderInterface;
use Marko\Testing\Fake\FakeAuthenticatable;
use Marko\Testing\Fake\FakeUserProvider;

it('FakeUserProvider implements UserProviderInterface', function () {
    $provider = new FakeUserProvider();

    expect($provider)->toBeInstanceOf(UserProviderInterface::class);
});

it('FakeUserProvider returns configured user by ID', function () {
    $user = new FakeAuthenticatable(id: 42);
    $provider = new FakeUserProvider(users: [42 => $user]);

    expect($provider->retrieveById(42))->toBe($user);
});

it('FakeUserProvider returns null when user not found by ID', function () {
    $provider = new FakeUserProvider(users: []);

    expect($provider->retrieveById(99))->toBeNull();
});

it('FakeUserProvider validates credentials using configurable callback', function () {
    $user = new FakeAuthenticatable(id: 1);

    $providerWithValidator = new FakeUserProvider(
        users: [1 => $user],
        credentialValidator: fn ($u, $creds) => $creds['password'] === 'correct',
    );

    expect($providerWithValidator->validateCredentials($user, ['password' => 'correct']))->toBeTrue()
        ->and($providerWithValidator->validateCredentials($user, ['password' => 'wrong']))->toBeFalse();

    $providerDefault = new FakeUserProvider(users: [1 => $user]);

    expect($providerDefault->validateCredentials($user, ['password' => 'anything']))->toBeTrue();
});

it('FakeUserProvider retrieves user by remember token', function () {
    $user = new FakeAuthenticatable(id: 1, rememberToken: 'valid-token');
    $provider = new FakeUserProvider(users: [1 => $user]);

    expect($provider->retrieveByRememberToken(1, 'valid-token'))->toBe($user)
        ->and($provider->retrieveByRememberToken(1, 'wrong-token'))->toBeNull()
        ->and($provider->retrieveByRememberToken(99, 'valid-token'))->toBeNull();
});

it('FakeUserProvider tracks remember token updates', function () {
    $user = new FakeAuthenticatable(id: 1);
    $provider = new FakeUserProvider(users: [1 => $user]);

    expect($provider->lastRememberTokenUpdate)->toBeNull();

    $provider->updateRememberToken($user, 'new-token');

    expect($provider->lastRememberTokenUpdate)->toBe(['user' => $user, 'token' => 'new-token'])
        ->and($user->getRememberToken())->toBe('new-token');

    $provider->updateRememberToken($user, null);

    expect($provider->lastRememberTokenUpdate)->toBe(['user' => $user, 'token' => null])
        ->and($user->getRememberToken())->toBeNull();
});
