<?php

declare(strict_types=1);

use Marko\Auth\Authenticatable;

it('creates Authenticatable trait implementing interface methods', function () {
    $trait = new ReflectionClass(Authenticatable::class);

    expect($trait->isTrait())->toBeTrue();

    // Check all interface methods are implemented
    $interfaceMethods = [
        'getAuthIdentifier',
        'getAuthIdentifierName',
        'getAuthPassword',
        'getRememberToken',
        'setRememberToken',
        'getRememberTokenName',
    ];

    foreach ($interfaceMethods as $methodName) {
        expect($trait->hasMethod($methodName))->toBeTrue();
    }
});

it('returns id property as default identifier', function () {
    $user = new class ()
    {
        use Authenticatable;

        public int $id = 42;

        public string $password = 'hashed';

        public ?string $rememberToken = null;
    };

    expect($user->getAuthIdentifier())->toBe(42)
        ->and($user->getAuthIdentifierName())->toBe('id');
});

it('returns password property as default password field', function () {
    $user = new class ()
    {
        use Authenticatable;

        public int $id = 1;

        public string $password = '$2y$10$hashedpassword';

        public ?string $rememberToken = null;
    };

    expect($user->getAuthPassword())->toBe('$2y$10$hashedpassword');
});

it('returns rememberToken property for remember token', function () {
    $user = new class ()
    {
        use Authenticatable;

        public int $id = 1;

        public string $password = 'hashed';

        public ?string $rememberToken = null;
    };

    // Initially null
    expect($user->getRememberToken())->toBeNull()
        ->and($user->getRememberTokenName())->toBe('remember_token');

    // Set token
    $user->setRememberToken('abc123xyz');

    expect($user->getRememberToken())->toBe('abc123xyz');

    // Clear token
    $user->setRememberToken(null);

    expect($user->getRememberToken())->toBeNull();
});
