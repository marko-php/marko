<?php

declare(strict_types=1);

use Marko\Authentication\Config\AuthConfig;
use Marko\Authentication\Contracts\UserProviderInterface;
use Marko\Authentication\Exceptions\AuthException;
use Marko\Authentication\UserProviderResolver;
use Marko\Core\Container\Container;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeUserProvider;

class ResolverAdminProvider extends FakeUserProvider {}

class ResolverCustomerProvider extends FakeUserProvider {}

/**
 * @param array<string, mixed> $config
 */
function userProviderResolver(
    array $config,
    ?UserProviderInterface $boundProvider = null,
): UserProviderResolver {
    $container = new Container();

    if ($boundProvider !== null) {
        $container->instance(UserProviderInterface::class, $boundProvider);
    }

    return new UserProviderResolver(new AuthConfig(new FakeConfigRepository($config)), $container);
}

it('resolves the provider class a guard names through authentication.providers', function (): void {
    $resolver = userProviderResolver([
        'authentication.providers' => [
            'admins' => ['class' => ResolverAdminProvider::class],
            'customers' => ['class' => ResolverCustomerProvider::class],
        ],
    ]);

    expect($resolver->forGuard('admin', ['driver' => 'session', 'provider' => 'admins']))
        ->toBeInstanceOf(ResolverAdminProvider::class)
        ->and($resolver->forGuard('web', ['driver' => 'session', 'provider' => 'customers']))
        ->toBeInstanceOf(ResolverCustomerProvider::class);
});

it('shares one provider instance per provider name', function (): void {
    $resolver = userProviderResolver([
        'authentication.providers' => ['admins' => ['class' => ResolverAdminProvider::class]],
    ]);

    $first = $resolver->forGuard('admin', ['provider' => 'admins']);

    expect($resolver->forGuard('admin-api', ['provider' => 'admins']))->toBe($first);
});

it('uses the UserProviderInterface binding when no authentication.providers config exists', function (): void {
    $bound = new FakeUserProvider();

    expect(userProviderResolver([], $bound)->forGuard('session', ['driver' => 'session', 'provider' => 'users']))
        ->toBe($bound);
});

it('uses the UserProviderInterface binding for a provider entry without a class', function (): void {
    $bound = new FakeUserProvider();
    $resolver = userProviderResolver([
        'authentication.providers' => ['users' => []],
    ], $bound);

    expect($resolver->forGuard('session', ['driver' => 'session', 'provider' => 'users']))->toBe($bound);
});

it('falls back to authentication.default.provider for a guard without a provider key', function (): void {
    $resolver = userProviderResolver([
        'authentication.default.provider' => 'admins',
        'authentication.providers' => ['admins' => ['class' => ResolverAdminProvider::class]],
    ]);

    expect($resolver->forGuard('admin', ['driver' => 'session']))->toBeInstanceOf(ResolverAdminProvider::class);
});

it('uses the UserProviderInterface binding for a guard with no provider name at all', function (): void {
    $bound = new FakeUserProvider();

    expect(userProviderResolver([], $bound)->forGuard('session', ['driver' => 'session']))->toBe($bound);
});

it('throws a helpful error for a provider name missing from authentication.providers', function (): void {
    $resolver = userProviderResolver([
        'authentication.providers' => ['users' => []],
    ]);

    expect(fn (): UserProviderInterface => $resolver->forGuard('admin', ['provider' => 'admins']))
        ->toThrow(AuthException::class, "User provider 'admins' is not defined in authentication.providers");
});

it('throws for a provider class that does not implement UserProviderInterface', function (): void {
    $resolver = userProviderResolver([
        'authentication.providers' => ['admins' => ['class' => stdClass::class]],
    ]);

    expect(fn (): UserProviderInterface => $resolver->forGuard('admin', ['provider' => 'admins']))
        ->toThrow(AuthException::class, "User provider 'admins' has an invalid class 'stdClass'");
});

it('throws for a guard provider that is not a non-empty string', function (): void {
    $resolver = userProviderResolver([]);

    expect(fn (): UserProviderInterface => $resolver->forGuard('admin', ['provider' => '']))
        ->toThrow(AuthException::class, "Guard 'admin' has an invalid provider");
});

it('resolves a provider by name through authentication.providers', function (): void {
    $resolver = userProviderResolver([
        'authentication.providers' => ['admins' => ['class' => ResolverAdminProvider::class]],
    ]);

    expect($resolver->forProvider('admins'))->toBeInstanceOf(ResolverAdminProvider::class)
        ->and($resolver->forProvider('admins'))->toBe($resolver->forGuard('admin', ['provider' => 'admins']));
});

it('uses the UserProviderInterface binding for a named provider without a class', function (): void {
    $bound = new FakeUserProvider();

    expect(userProviderResolver(['authentication.providers' => ['users' => []]], $bound)->forProvider('users'))
        ->toBe($bound)
        ->and(userProviderResolver([], $bound)->forProvider('users'))->toBe($bound);
});

it('throws a helpful error when a named provider is missing from authentication.providers', function (): void {
    $resolver = userProviderResolver(['authentication.providers' => ['users' => []]]);

    expect(fn (): UserProviderInterface => $resolver->forProvider('admins'))
        ->toThrow(AuthException::class, "User provider 'admins' is not defined in authentication.providers");
});
