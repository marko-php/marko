<?php

declare(strict_types=1);

use Marko\Authentication\CanResetPasswordInterface;
use Marko\Authentication\Config\AuthConfig;
use Marko\Authentication\Contracts\ResetLinkNotifierInterface;
use Marko\Authentication\Contracts\UserPersisterInterface;
use Marko\Authentication\Contracts\UserProviderInterface;
use Marko\Authentication\Database\Tests\Fixtures\PasswordResetTokenTable;
use Marko\Authentication\Database\Tests\Fixtures\ResettableUser;
use Marko\Authentication\Database\Tests\Fixtures\SqlitePasswordResetTokenConnection;
use Marko\Authentication\Hashing\BcryptPasswordHasher;
use Marko\Authentication\PasswordReset\PasswordResetBroker;
use Marko\Authentication\PasswordReset\ResetLinkStatus;
use Marko\Authentication\PasswordReset\ResetStatus;
use Marko\Authentication\UserPersisterResolver;
use Marko\Authentication\UserProviderResolver;
use Marko\Cache\CacheOperations;
use Marko\Cache\Config\CacheConfig;
use Marko\Cache\Memory\Driver\ArrayCacheDriver;
use Marko\Core\Container\Container;
use Marko\Database\PgSql\Query\PgSqlQueryBuilderFactory;
use Marko\RateLimiter\RateLimiter;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeEventDispatcher;
use Marko\Testing\Fake\FakeUserPersister;
use Marko\Testing\Fake\FakeUserProvider;

/*
 * The full "forgot password" flow of PasswordResetBroker with its tokens in the password_reset_tokens table.
 */

beforeEach(function (): void {
    $this->clock = new FakeClock(new DateTimeImmutable('2026-10-06 12:00:00 UTC'));
    $this->connection = new SqlitePasswordResetTokenConnection();
    $this->user = new ResettableUser(id: 7);
    $this->persister = new FakeUserPersister();
    $this->notifier = new class () implements ResetLinkNotifierInterface
    {
        public string $token = '';

        public function send(
            CanResetPasswordInterface $user,
            string $token,
        ): void {
            $this->token = $token;
        }
    };

    $container = new Container();
    $container->instance(UserProviderInterface::class, new FakeUserProvider([7 => $this->user]));
    $container->instance(UserPersisterInterface::class, $this->persister);
    $authConfig = new AuthConfig(new FakeConfigRepository([
        'authentication.password_reset.provider' => 'users',
        'authentication.password_reset.expire_seconds' => 3600,
        'authentication.password_reset.throttle_seconds' => 60,
    ]));
    $cache = new ArrayCacheDriver(new CacheConfig(new FakeConfigRepository([
        'cache.path' => sys_get_temp_dir(),
        'cache.default_ttl' => 3600,
        'cache.driver' => 'array',
    ])), $this->clock, new CacheOperations());

    $this->broker = new PasswordResetBroker(
        authConfig: $authConfig,
        userProviderResolver: new UserProviderResolver($authConfig, $container),
        userPersisterResolver: new UserPersisterResolver($authConfig, $container),
        passwordResetTokenRepository: PasswordResetTokenTable::repository(
            $this->connection,
            new PgSqlQueryBuilderFactory($this->connection),
        ),
        resetLinkNotifier: $this->notifier,
        passwordHasher: new BcryptPasswordHasher(cost: 4),
        rateLimiter: new RateLimiter($cache, $this->clock),
        eventDispatcher: new FakeEventDispatcher(),
        clock: $this->clock,
    );
});

it('stores the hash of the sent token and redeems it once', function (): void {
    $sent = $this->broker->sendResetLink(['identifier' => 7]);
    $stored = $this->connection->rows();

    $reset = $this->broker->reset(['identifier' => 7], $this->notifier->token, 'new-password');
    $replayed = $this->broker->reset(['identifier' => 7], $this->notifier->token, 'other-password');

    expect($sent)->toBe(ResetLinkStatus::Sent)
        ->and($stored[0]['token_hash'])->toBe(hash('sha256', $this->notifier->token))
        ->and($reset)->toBe(ResetStatus::PasswordReset)
        ->and(password_verify('new-password', $this->user->getAuthPassword()))->toBeTrue()
        ->and($this->persister->wasPersisted($this->user))->toBeTrue()
        ->and($replayed)->toBe(ResetStatus::InvalidToken)
        ->and($this->connection->rows())->toBe([]);
});

it('rejects a stored token once it is expire_seconds old', function (): void {
    $this->broker->sendResetLink(['identifier' => 7]);
    $this->clock->travel('+3600 seconds');

    $status = $this->broker->reset(['identifier' => 7], $this->notifier->token, 'new-password');

    expect($status)->toBe(ResetStatus::InvalidToken);
    $this->persister->assertNothingPersisted();
});
