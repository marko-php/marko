<?php

declare(strict_types=1);

use Marko\Authentication\AuthManager;
use Marko\Authentication\Config\AuthConfig;
use Marko\Authentication\Contracts\GuardInterface;
use Marko\Authentication\Exceptions\AuthException;
use Marko\Authentication\Guard\GuardDriverRegistry;
use Marko\Authentication\Hashing\BcryptPasswordHasher;
use Marko\Authentication\Hashing\PasswordVerifier;
use Marko\Authentication\Tests\Fixtures\BoundProviderResolver;
use Marko\Authentication\Token\RememberTokenManager;
use Marko\Authentication\Validation\AuthRules;
use Marko\Authentication\Validation\CurrentPassword;
use Marko\Authentication\Validation\Password;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Testing\Fake\FakeAuthenticatable;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeCookieJar;
use Marko\Testing\Fake\FakeEventDispatcher;
use Marko\Testing\Fake\FakeGuard;
use Marko\Testing\Fake\FakeSession;
use Marko\Testing\Fake\FakeUserProvider;

/**
 * @return array<string, mixed>
 */
function passwordDefaults(): array
{
    return [
        'authentication.password.min' => 8,
        'authentication.password.mixed_case' => false,
        'authentication.password.letters' => false,
        'authentication.password.numbers' => false,
        'authentication.password.symbols' => false,
    ];
}

/**
 * AuthRules over an AuthManager whose 'web' and 'admin' guards are FakeGuards,
 * with a password-defaults config that $overrides can change or remove keys from.
 *
 * @param array<string, mixed> $overrides
 * @param list<string> $without
 * @param array<string, FakeGuard> $guards
 */
function authRules(
    array $overrides = [],
    array $without = [],
    array $guards = [],
): AuthRules {
    $values = [
        'authentication.remember.cookie.prefix' => 'remember_',
        'authentication.default.guard' => 'web',
        'authentication.guards' => [
            'web' => ['driver' => 'fake', 'provider' => 'users'],
            'admin' => ['driver' => 'fake', 'provider' => 'users'],
        ],
        ...passwordDefaults(),
        ...$overrides,
    ];

    foreach ($without as $key) {
        unset($values[$key]);
    }

    $config = new AuthConfig(new FakeConfigRepository($values));
    $registry = new GuardDriverRegistry();
    $registry->extend(
        'fake',
        fn (string $name): GuardInterface => $guards[$name] ?? new FakeGuard(name: $name),
    );
    $session = new FakeSession();
    $session->start();

    $manager = new AuthManager(
        config: $config,
        session: $session,
        providerResolver: BoundProviderResolver::for(new FakeUserProvider()),
        eventDispatcher: new FakeEventDispatcher(),
        cookieJar: new FakeCookieJar(),
        rememberTokenManager: new RememberTokenManager(new FakeClock()),
        guardDriverRegistry: $registry,
    );

    return new AuthRules(
        authManager: $manager,
        passwordVerifier: new PasswordVerifier(new BcryptPasswordHasher(cost: 4)),
        config: $config,
    );
}

function guardWithPassword(
    string $name,
    string $password,
): FakeGuard {
    $guard = new FakeGuard(name: $name);
    $guard->setUser(new FakeAuthenticatable(password: new BcryptPasswordHasher(cost: 4)->hash($password)));

    return $guard;
}

describe('currentPassword', function (): void {
    it('checks the default guard\'s user', function (): void {
        $rules = authRules(guards: ['web' => guardWithPassword('web', 'web-secret')]);

        $rule = $rules->currentPassword();

        expect($rule)->toBeInstanceOf(CurrentPassword::class)
            ->and($rule->passes('current_password', 'web-secret', []))->toBeTrue();
    });

    it('checks a named guard\'s user', function (): void {
        $rules = authRules(guards: [
            'web' => guardWithPassword('web', 'web-secret'),
            'admin' => guardWithPassword('admin', 'admin-secret'),
        ]);

        $rule = $rules->currentPassword('admin');

        expect($rule->passes('current_password', 'admin-secret', []))->toBeTrue()
            ->and($rule->passes('current_password', 'web-secret', []))->toBeFalse();
    });

    it('fails when the named guard has no authenticated user', function (): void {
        $rules = authRules(guards: ['web' => guardWithPassword('web', 'secret')]);

        expect($rules->currentPassword('admin')->passes('current_password', 'secret', []))->toBeFalse();
    });
});

describe('password', function (): void {
    it('builds the configured defaults', function (): void {
        expect(authRules()->password())->toBeInstanceOf(Password::class);
    });

    it('reads every password config key', function (string $key, mixed $value, string $failing): void {
        $defaults = authRules()->password();
        $configured = authRules(overrides: [$key => $value])->password();

        expect($defaults->passes('password', $failing, []))->toBeTrue()
            ->and($configured->passes('password', $failing, []))->toBeFalse();
    })->with([
        ['authentication.password.min', 12, 'elevenchars'],
        ['authentication.password.mixed_case', true, 'lowercase'],
        ['authentication.password.letters', true, '12345678'],
        ['authentication.password.numbers', true, 'noNumbers'],
        ['authentication.password.symbols', true, 'noSymbols'],
    ]);

    it('throws when a password config key is missing', function (string $key): void {
        expect(fn () => authRules(without: [$key])->password())
            ->toThrow(ConfigNotFoundException::class);
    })->with(array_keys(passwordDefaults()));

    it('rejects a configured minimum below one', function (): void {
        expect(fn () => authRules(overrides: ['authentication.password.min' => 0])->password())
            ->toThrow(AuthException::class, 'authentication.password.min must be a positive integer, got 0');
    });

    it('tightens the defaults for one use without changing them', function (): void {
        $rules = authRules();

        $strict = $rules->password()->min(16);

        expect($strict->passes('password', 'tenletters', []))->toBeFalse()
            ->and($rules->password()->passes('password', 'tenletters', []))->toBeTrue();
    });
});

it('ships password rule defaults in the package config file', function (): void {
    $defaults = require dirname(__DIR__, 3) . '/config/authentication.php';

    expect($defaults['password'])->toMatchArray([
        'min' => 8,
        'mixed_case' => false,
        'letters' => false,
        'numbers' => false,
        'symbols' => false,
    ]);
});
