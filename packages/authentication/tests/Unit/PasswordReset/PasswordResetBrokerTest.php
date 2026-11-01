<?php

declare(strict_types=1);

use Marko\Authentication\AuthenticatableInterface;
use Marko\Authentication\Config\AuthConfig;
use Marko\Authentication\Contracts\PasswordResetBrokerInterface;
use Marko\Authentication\Contracts\UserPersisterInterface;
use Marko\Authentication\Contracts\UserProviderInterface;
use Marko\Authentication\Event\PasswordResetEvent;
use Marko\Authentication\Exceptions\UserPersisterException;
use Marko\Authentication\Hashing\BcryptPasswordHasher;
use Marko\Authentication\PasswordReset\PasswordResetBroker;
use Marko\Authentication\PasswordReset\ResetLinkStatus;
use Marko\Authentication\PasswordReset\ResetStatus;
use Marko\Authentication\Tests\Fixtures\EmailUserProvider;
use Marko\Authentication\Tests\Fixtures\InMemoryPasswordResetTokenRepository;
use Marko\Authentication\Tests\Fixtures\RecordingResetLinkNotifier;
use Marko\Authentication\Tests\Fixtures\RecordingUserPersister;
use Marko\Authentication\Tests\Fixtures\ResettableUser;
use Marko\Authentication\UserPersisterResolver;
use Marko\Authentication\UserProviderResolver;
use Marko\Cache\Config\CacheConfig;
use Marko\Cache\Memory\Driver\ArrayCacheDriver;
use Marko\Core\Container\Container;
use Marko\RateLimiter\RateLimiter;
use Marko\Testing\Fake\FakeAuthenticatable;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeEventDispatcher;

class ProviderSpecificUserPersister extends RecordingUserPersister {}

/**
 * Everything a PasswordResetBroker test needs, wired to in-memory collaborators.
 */
class PasswordResetHarness
{
    public FakeClock $clock;

    public InMemoryPasswordResetTokenRepository $tokens;

    public RecordingResetLinkNotifier $notifier;

    public RecordingUserPersister $persister;

    public FakeEventDispatcher $events;

    public Container $container;

    public ResettableUser $user;

    /**
     * @param array<string, mixed> $config
     * @param array<string, AuthenticatableInterface>|null $users
     */
    public function __construct(
        private readonly array $config = [],
        ?array $users = null,
        bool $bindPersister = true,
    ) {
        $this->clock = new FakeClock('2026-06-01 12:00:00');
        $this->tokens = new InMemoryPasswordResetTokenRepository();
        $this->notifier = new RecordingResetLinkNotifier();
        $this->persister = new RecordingUserPersister();
        $this->events = new FakeEventDispatcher();
        $this->user = new ResettableUser(id: 7, email: 'ada@example.com');
        $this->container = new Container();
        $this->container->instance(
            UserProviderInterface::class,
            new EmailUserProvider($users ?? ['ada@example.com' => $this->user]),
        );

        if ($bindPersister) {
            $this->container->instance(UserPersisterInterface::class, $this->persister);
        }
    }

    public function broker(): PasswordResetBroker
    {
        $authConfig = new AuthConfig(new FakeConfigRepository([
            'authentication.password_reset.provider' => 'users',
            'authentication.password_reset.expire_seconds' => 3600,
            'authentication.password_reset.throttle_seconds' => 60,
            ...$this->config,
        ]));
        $cache = new ArrayCacheDriver(new CacheConfig(new FakeConfigRepository([
            'cache.path' => sys_get_temp_dir(),
            'cache.default_ttl' => 3600,
            'cache.driver' => 'array',
        ])), $this->clock);

        return new PasswordResetBroker(
            authConfig: $authConfig,
            userProviderResolver: new UserProviderResolver($authConfig, $this->container),
            userPersisterResolver: new UserPersisterResolver($authConfig, $this->container),
            passwordResetTokenRepository: $this->tokens,
            resetLinkNotifier: $this->notifier,
            passwordHasher: new BcryptPasswordHasher(cost: 4),
            rateLimiter: new RateLimiter($cache, $this->clock),
            eventDispatcher: $this->events,
            clock: $this->clock,
        );
    }

    public function sentToken(): string
    {
        return $this->notifier->sent[array_key_last($this->notifier->sent)]['token'];
    }
}

it('implements PasswordResetBrokerInterface', function (): void {
    expect(new PasswordResetHarness()->broker())->toBeInstanceOf(PasswordResetBrokerInterface::class);
});

it('sends a 64-byte random token to the notifier and stores only its SHA-256 hash', function (): void {
    $harness = new PasswordResetHarness();

    $status = $harness->broker()->sendResetLink(['email' => 'ada@example.com']);

    $token = $harness->sentToken();
    $record = $harness->tokens->find(7);

    expect($status)->toBe(ResetLinkStatus::Sent)
        ->and($harness->notifier->sent[0]['user'])->toBe($harness->user)
        ->and($token)->toMatch('/^[0-9a-f]{128}$/')
        ->and($record)->not->toBeNull()
        ->and($record->tokenHash)->toBe(hash('sha256', $token))
        ->and($record->tokenHash)->not->toBe($token)
        ->and($record->createdAt)->toEqual($harness->clock->now());
});

it('returns the same status for an unknown email and sends nothing', function (): void {
    $harness = new PasswordResetHarness();

    $status = $harness->broker()->sendResetLink(['email' => 'nobody@example.com']);

    expect($status)->toBe(ResetLinkStatus::Sent)
        ->and($harness->notifier->sent)->toBe([])
        ->and($harness->tokens->records)->toBe([]);
});

it('throttles repeat requests for the same user within throttle_seconds', function (): void {
    $harness = new PasswordResetHarness();
    $broker = $harness->broker();

    $first = $broker->sendResetLink(['email' => 'ada@example.com']);
    $harness->clock->travel('+30 seconds');
    $second = $broker->sendResetLink(['email' => 'ada@example.com']);

    expect($first)->toBe(ResetLinkStatus::Sent)
        ->and($second)->toBe(ResetLinkStatus::Throttled)
        ->and($harness->notifier->sent)->toHaveCount(1);
});

it('allows a new request once throttle_seconds have passed', function (): void {
    $harness = new PasswordResetHarness();
    $broker = $harness->broker();

    $broker->sendResetLink(['email' => 'ada@example.com']);
    $harness->clock->travel('+61 seconds');

    expect($broker->sendResetLink(['email' => 'ada@example.com']))->toBe(ResetLinkStatus::Sent)
        ->and($harness->notifier->sent)->toHaveCount(2);
});

it('throttles repeat requests for an unknown email the same way, so throttling does not reveal it', function (): void {
    $harness = new PasswordResetHarness();
    $broker = $harness->broker();

    $first = $broker->sendResetLink(['email' => 'nobody@example.com']);
    $second = $broker->sendResetLink(['email' => 'nobody@example.com']);

    expect($first)->toBe(ResetLinkStatus::Sent)
        ->and($second)->toBe(ResetLinkStatus::Throttled);
});

it('resets the password, persists the user, deletes the token and dispatches PasswordResetEvent', function (): void {
    $harness = new PasswordResetHarness();
    $broker = $harness->broker();
    $broker->sendResetLink(['email' => 'ada@example.com']);

    $status = $broker->reset(['email' => 'ada@example.com'], $harness->sentToken(), 'new-secret-password');

    $events = $harness->events->dispatched(PasswordResetEvent::class);

    expect($status)->toBe(ResetStatus::PasswordReset)
        ->and(password_verify('new-secret-password', $harness->user->getAuthPassword()))->toBeTrue()
        ->and($harness->persister->persisted)->toBe([$harness->user])
        ->and($harness->tokens->find(7))->toBeNull()
        ->and($events)->toHaveCount(1)
        ->and($events[0]->getUser())->toBe($harness->user);
});

it('rejects reusing a token that already reset the password', function (): void {
    $harness = new PasswordResetHarness();
    $broker = $harness->broker();
    $broker->sendResetLink(['email' => 'ada@example.com']);
    $token = $harness->sentToken();
    $broker->reset(['email' => 'ada@example.com'], $token, 'new-secret-password');

    $status = $broker->reset(['email' => 'ada@example.com'], $token, 'another-password');

    expect($status)->toBe(ResetStatus::InvalidToken)
        ->and(password_verify('new-secret-password', $harness->user->getAuthPassword()))->toBeTrue()
        ->and($harness->persister->persisted)->toHaveCount(1);
});

it('rejects a wrong token without changing the password', function (): void {
    $harness = new PasswordResetHarness();
    $broker = $harness->broker();
    $broker->sendResetLink(['email' => 'ada@example.com']);

    $status = $broker->reset(['email' => 'ada@example.com'], str_repeat('a', 128), 'new-secret-password');

    expect($status)->toBe(ResetStatus::InvalidToken)
        ->and($harness->user->getAuthPassword())->toBe('old-hash')
        ->and($harness->persister->persisted)->toBe([])
        ->and($harness->tokens->find(7))->not->toBeNull()
        ->and($harness->events->dispatched(PasswordResetEvent::class))->toBe([]);
});

it('rejects a token older than expire_seconds and deletes it', function (): void {
    $harness = new PasswordResetHarness();
    $broker = $harness->broker();
    $broker->sendResetLink(['email' => 'ada@example.com']);
    $harness->clock->travel('+3600 seconds');

    $status = $broker->reset(['email' => 'ada@example.com'], $harness->sentToken(), 'new-secret-password');

    expect($status)->toBe(ResetStatus::InvalidToken)
        ->and($harness->user->getAuthPassword())->toBe('old-hash')
        ->and($harness->tokens->find(7))->toBeNull();
});

it('accepts a token just inside expire_seconds', function (): void {
    $harness = new PasswordResetHarness();
    $broker = $harness->broker();
    $broker->sendResetLink(['email' => 'ada@example.com']);
    $harness->clock->travel('+3599 seconds');

    expect($broker->reset(['email' => 'ada@example.com'], $harness->sentToken(), 'new-secret-password'))
        ->toBe(ResetStatus::PasswordReset);
});

it('rejects a token when no reset was requested', function (): void {
    $harness = new PasswordResetHarness();

    expect($harness->broker()->reset(['email' => 'ada@example.com'], str_repeat('a', 128), 'new-secret-password'))
        ->toBe(ResetStatus::InvalidToken);
});

it('returns InvalidUser when the credentials match no user', function (): void {
    $harness = new PasswordResetHarness();

    expect($harness->broker()->reset(['email' => 'nobody@example.com'], 'token', 'new-secret-password'))
        ->toBe(ResetStatus::InvalidUser);
});

it('throws naming the provider when no persister can be resolved', function (): void {
    $harness = new PasswordResetHarness(bindPersister: false);

    expect(fn () => $harness->broker()->sendResetLink(['email' => 'ada@example.com']))
        ->toThrow(UserPersisterException::class, "user provider 'users'");
});

it('throws naming the provider when the user does not implement CanResetPasswordInterface', function (): void {
    $harness = new PasswordResetHarness(users: ['ada@example.com' => new FakeAuthenticatable(id: 7)]);

    expect(fn () => $harness->broker()->sendResetLink(['email' => 'ada@example.com']))
        ->toThrow(UserPersisterException::class, "user provider 'users'")
        ->and(fn () => $harness->broker()->reset(['email' => 'ada@example.com'], 'token', 'new-password'))
        ->toThrow(UserPersisterException::class, 'CanResetPasswordInterface');
});

it('persists through the persister named in the provider config instead of the container binding', function (): void {
    $harness = new PasswordResetHarness(config: [
        'authentication.providers' => ['users' => ['persister' => ProviderSpecificUserPersister::class]],
    ]);
    $providerPersister = new ProviderSpecificUserPersister();
    $harness->container->instance(ProviderSpecificUserPersister::class, $providerPersister);
    $broker = $harness->broker();
    $broker->sendResetLink(['email' => 'ada@example.com']);

    $broker->reset(['email' => 'ada@example.com'], $harness->sentToken(), 'new-secret-password');

    expect($providerPersister->persisted)->toBe([$harness->user])
        ->and($harness->persister->persisted)->toBe([]);
});

it('looks users up through the provider named by password_reset.provider', function (): void {
    $customer = new ResettableUser(id: 99, email: 'cust@example.com');
    $harness = new PasswordResetHarness(config: [
        'authentication.password_reset.provider' => 'customers',
        'authentication.providers' => ['users' => [], 'customers' => ['class' => EmailUserProvider::class]],
    ]);
    $harness->container->instance(EmailUserProvider::class, new EmailUserProvider(['cust@example.com' => $customer]));

    $harness->broker()->sendResetLink(['email' => 'cust@example.com']);

    expect($harness->notifier->sent[0]['user'])->toBe($customer);
});
