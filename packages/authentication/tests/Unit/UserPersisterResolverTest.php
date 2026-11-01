<?php

declare(strict_types=1);

use Marko\Authentication\CanResetPasswordInterface;
use Marko\Authentication\Config\AuthConfig;
use Marko\Authentication\Contracts\UserPersisterInterface;
use Marko\Authentication\Exceptions\UserPersisterException;
use Marko\Authentication\Tests\Fixtures\RecordingUserPersister;
use Marko\Authentication\Tests\Fixtures\ResettableUser;
use Marko\Authentication\UserPersisterResolver;
use Marko\Core\Container\Container;
use Marko\Testing\Fake\FakeAuthenticatable;
use Marko\Testing\Fake\FakeConfigRepository;

class ResolverAdminPersister extends RecordingUserPersister {}

/**
 * @param array<string, mixed> $config
 */
function userPersisterResolver(
    array $config,
    ?UserPersisterInterface $boundPersister = null,
): UserPersisterResolver {
    $container = new Container();

    if ($boundPersister !== null) {
        $container->instance(UserPersisterInterface::class, $boundPersister);
    }

    return new UserPersisterResolver(new AuthConfig(new FakeConfigRepository($config)), $container);
}

it('resolves the persister class a provider names with its persister key', function (): void {
    $resolver = userPersisterResolver(
        ['authentication.providers' => ['admins' => ['persister' => ResolverAdminPersister::class]]],
        new RecordingUserPersister(),
    );

    expect($resolver->forProvider('admins'))->toBeInstanceOf(ResolverAdminPersister::class);
});

it('shares one persister instance per provider name', function (): void {
    $resolver = userPersisterResolver([
        'authentication.providers' => ['admins' => ['persister' => ResolverAdminPersister::class]],
    ]);

    expect($resolver->forProvider('admins'))->toBe($resolver->forProvider('admins'));
});

it('uses the UserPersisterInterface binding when the provider names no persister', function (): void {
    $bound = new RecordingUserPersister();

    $resolver = userPersisterResolver(['authentication.providers' => ['users' => []]], $bound);

    expect($resolver->forProvider('users'))->toBe($bound);
});

it('uses the UserPersisterInterface binding when no authentication.providers config exists', function (): void {
    $bound = new RecordingUserPersister();

    expect(userPersisterResolver([], $bound)->forProvider('users'))->toBe($bound);
});

it('throws naming the provider when no persister is configured or bound', function (): void {
    $resolver = userPersisterResolver(['authentication.providers' => ['users' => []]]);

    expect(fn () => $resolver->forProvider('users'))
        ->toThrow(UserPersisterException::class, "No user persister is configured for user provider 'users'");
});

it('throws naming the provider when its persister class does not implement UserPersisterInterface', function (): void {
    $resolver = userPersisterResolver([
        'authentication.providers' => ['users' => ['persister' => stdClass::class]],
    ]);

    expect(fn () => $resolver->forProvider('users'))
        ->toThrow(UserPersisterException::class, "User provider 'users' has an invalid persister class 'stdClass'");
});

it('throws when the provider is not defined in authentication.providers', function (): void {
    $resolver = userPersisterResolver(['authentication.providers' => ['users' => []]], new RecordingUserPersister());

    expect(fn () => $resolver->forProvider('admins'))
        ->toThrow(UserPersisterException::class, "User provider 'admins' is not defined in authentication.providers");
});

it('accepts a user that implements the feature interface', function (): void {
    $resolver = userPersisterResolver([]);

    $resolver->assertUserSupports(new ResettableUser(1, 'a@example.com'), CanResetPasswordInterface::class, 'users');

    expect(true)->toBeTrue();
});

it(
    'throws naming the provider, the user class and the interface when the user lacks the feature interface',
    function (): void {
        $resolver = userPersisterResolver([]);

        try {
            $resolver->assertUserSupports(new FakeAuthenticatable(), CanResetPasswordInterface::class, 'users');
            $this->fail('Expected UserPersisterException');
        } catch (UserPersisterException $exception) {
            expect($exception->getMessage())
                ->toContain("user provider 'users'")
                ->toContain(FakeAuthenticatable::class)
                ->toContain(CanResetPasswordInterface::class);
        }
    },
);
