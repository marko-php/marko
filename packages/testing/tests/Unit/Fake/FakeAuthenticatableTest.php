<?php

declare(strict_types=1);

use Marko\Authentication\AuthenticatableInterface;
use Marko\Testing\Fake\FakeAuthenticatable;

it('FakeAuthenticatable implements AuthenticatableInterface', function () {
    $user = new FakeAuthenticatable();

    expect($user)->toBeInstanceOf(AuthenticatableInterface::class);
});

it('FakeAuthenticatable has configurable identifier, password, and remember token', function () {
    $user = new FakeAuthenticatable(
        id: 42,
        password: 'secret',
        rememberToken: 'token123',
    );

    expect($user->getAuthIdentifier())->toBe(42)
        ->and($user->getAuthPassword())->toBe('secret')
        ->and($user->getRememberToken())->toBe('token123');
});

it('FakeAuthenticatable defaults to sensible values (id=1, password=hashed-password)', function () {
    $user = new FakeAuthenticatable();

    expect($user->getAuthIdentifier())->toBe(1)
        ->and($user->getAuthPassword())->toBe('hashed-password')
        ->and($user->getRememberToken())->toBeNull()
        ->and($user->getAuthIdentifierName())->toBe('id')
        ->and($user->getRememberTokenName())->toBe('remember_token');
});

it('FakeAuthenticatable tracks remember token changes', function () {
    $user = new FakeAuthenticatable();

    expect($user->getRememberToken())->toBeNull();

    $user->setRememberToken('abc-token');

    expect($user->getRememberToken())->toBe('abc-token');

    $user->setRememberToken(null);

    expect($user->getRememberToken())->toBeNull();
});
