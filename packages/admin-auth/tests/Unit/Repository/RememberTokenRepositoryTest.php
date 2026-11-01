<?php

declare(strict_types=1);

use Marko\AdminAuth\Repository\RememberTokenRepository;
use Marko\AdminAuth\Tests\Fixtures\SqliteRememberTokenConnection;
use Marko\Authentication\Command\ClearTokensCommand;
use Marko\Authentication\Contracts\RememberTokenStorageInterface;
use Marko\Authentication\Guard\SessionGuard;
use Marko\Authentication\Token\RememberTokenManager;
use Marko\Authentication\Token\RememberTokenRecord;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Testing\Fake\FakeAuthenticatable;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeCookieJar;
use Marko\Testing\Fake\FakeSession;
use Marko\Testing\Fake\FakeUserProvider;

function rememberTokenRepository(
    SqliteRememberTokenConnection $connection,
    FakeClock $clock,
): RememberTokenRepository {
    $metadataFactory = new EntityMetadataFactory();

    return new RememberTokenRepository(
        connection: $connection,
        metadataFactory: $metadataFactory,
        hydrator: new EntityHydrator($metadataFactory),
        clock: $clock,
    );
}

function rememberTokenRecord(
    string $selector,
    DateTimeImmutable $expiresAt,
    string $guard = 'admin',
    int|string $userId = 1,
): RememberTokenRecord {
    return new RememberTokenRecord(
        guard: $guard,
        userId: $userId,
        selector: $selector,
        validatorHash: hash('sha256', "validator-$selector"),
        expiresAt: $expiresAt,
        userAgent: 'Safari on iPhone',
    );
}

beforeEach(function (): void {
    $this->clock = new FakeClock('2026-03-01 09:00:00');
    $this->connection = new SqliteRememberTokenConnection();
    $this->repository = rememberTokenRepository($this->connection, $this->clock);
});

it('is bound as the remember token storage by the module', function (): void {
    $module = require dirname(__DIR__, 3) . '/module.php';

    expect($module['bindings'][RememberTokenStorageInterface::class])->toBe(RememberTokenRepository::class)
        ->and($this->repository)->toBeInstanceOf(RememberTokenStorageInterface::class);
});

it('stores a device token and finds it again by guard and selector', function (): void {
    $expiresAt = $this->clock->now()->modify('+30 days');

    $this->repository->store(rememberTokenRecord('abc', $expiresAt, userId: 42));

    $found = $this->repository->findBySelector('admin', 'abc');

    expect($found)->toBeInstanceOf(RememberTokenRecord::class)
        ->and($found->guard)->toBe('admin')
        ->and($found->userId)->toBe('42')
        ->and($found->selector)->toBe('abc')
        ->and($found->validatorHash)->toBe(hash('sha256', 'validator-abc'))
        ->and($found->expiresAt->getTimestamp())->toBe($expiresAt->getTimestamp())
        ->and($found->userAgent)->toBe('Safari on iPhone')
        ->and($this->connection->rows()[0]['created_at'])->toBe('2026-03-01 09:00:00');
});

it('does not find a selector under another guard', function (): void {
    $this->repository->store(rememberTokenRecord('abc', $this->clock->now()->modify('+1 day')));

    expect($this->repository->findBySelector('session', 'abc'))->toBeNull()
        ->and($this->repository->findBySelector('admin', 'missing'))->toBeNull();
});

it('rotates the validator only while the current hash still matches', function (): void {
    $this->repository->store(rememberTokenRecord('abc', $this->clock->now()->modify('+1 day')));
    $current = hash('sha256', 'validator-abc');

    $first = $this->repository->rotateValidator('admin', 'abc', $current, 'new-hash');
    $second = $this->repository->rotateValidator('admin', 'abc', $current, 'other-hash');

    expect($first)->toBeTrue()
        ->and($second)->toBeFalse()
        ->and($this->repository->findBySelector('admin', 'abc')->validatorHash)->toBe('new-hash');
});

it('deletes one device token and leaves the others', function (): void {
    $expiresAt = $this->clock->now()->modify('+1 day');
    $this->repository->store(rememberTokenRecord('laptop', $expiresAt));
    $this->repository->store(rememberTokenRecord('phone', $expiresAt));

    $this->repository->deleteBySelector('admin', 'laptop');

    expect($this->repository->findBySelector('admin', 'laptop'))->toBeNull()
        ->and($this->repository->findBySelector('admin', 'phone'))->not->toBeNull();
});

it('deletes every token of one user on a guard except the kept selector', function (): void {
    $expiresAt = $this->clock->now()->modify('+1 day');
    $this->repository->store(rememberTokenRecord('laptop', $expiresAt, userId: 7));
    $this->repository->store(rememberTokenRecord('phone', $expiresAt, userId: 7));
    $this->repository->store(rememberTokenRecord('tablet', $expiresAt, userId: 7));
    $this->repository->store(rememberTokenRecord('other-user', $expiresAt, userId: 8));
    $this->repository->store(rememberTokenRecord('other-guard', $expiresAt, guard: 'session', userId: 7));

    $deleted = $this->repository->deleteAllForUser('admin', 7, exceptSelector: 'laptop');

    expect($deleted)->toBe(2)
        ->and(array_column($this->connection->rows(), 'selector'))->toBe(['laptop', 'other-user', 'other-guard']);
});

it('deletes every token of one user on a guard when no selector is kept', function (): void {
    $expiresAt = $this->clock->now()->modify('+1 day');
    $this->repository->store(rememberTokenRecord('laptop', $expiresAt, userId: 7));
    $this->repository->store(rememberTokenRecord('phone', $expiresAt, userId: 7));
    $this->repository->store(rememberTokenRecord('other-user', $expiresAt, userId: 8));

    expect($this->repository->deleteAllForUser('admin', '7'))->toBe(2)
        ->and(array_column($this->connection->rows(), 'selector'))->toBe(['other-user']);
});

it('purges only expired rows', function (): void {
    $this->repository->store(rememberTokenRecord('expired', $this->clock->now()->modify('-1 second')));
    $this->repository->store(rememberTokenRecord('expiring-now', $this->clock->now()));
    $this->repository->store(rememberTokenRecord('live', $this->clock->now()->modify('+1 minute')));

    expect($this->repository->clearExpiredTokens())->toBe(2)
        ->and(array_column($this->connection->rows(), 'selector'))->toBe(['live']);
});

it('purges expired rows through auth:clear-tokens', function (): void {
    $this->repository->store(rememberTokenRecord('expired', $this->clock->now()->modify('-1 day')));
    $this->repository->store(rememberTokenRecord('live', $this->clock->now()->modify('+1 day')));
    $stream = fopen('php://memory', 'r+');

    $exitCode = new ClearTokensCommand($this->repository)->execute(
        new Input(['marko', 'auth:clear-tokens']),
        new Output($stream),
    );
    rewind($stream);

    expect($exitCode)->toBe(0)
        ->and(stream_get_contents($stream))->toContain('Cleared 1 expired token(s).')
        ->and(array_column($this->connection->rows(), 'selector'))->toBe(['live']);
});

it('clears every row with clearAllTokens', function (): void {
    $this->repository->store(rememberTokenRecord('one', $this->clock->now()->modify('+1 day')));
    $this->repository->store(rememberTokenRecord('two', $this->clock->now()->modify('+1 day'), guard: 'session'));

    expect($this->repository->clearAllTokens())->toBe(2)
        ->and($this->connection->rows())->toBe([]);
});

describe('SessionGuard over the remember_tokens table', function (): void {
    beforeEach(function (): void {
        $this->tokenManager = new RememberTokenManager($this->clock, lifetimeMinutes: 60);
        $this->user = new FakeAuthenticatable(id: 5);
        $this->provider = new FakeUserProvider([5 => $this->user]);
        $this->guardFor = function (FakeCookieJar $cookieJar): SessionGuard {
            $session = new FakeSession();
            $session->start();

            return new SessionGuard(
                session: $session,
                provider: $this->provider,
                name: 'admin',
                cookieJar: $cookieJar,
                tokenManager: $this->tokenManager,
                rememberTokenStorage: $this->repository,
            );
        };
    });

    it('keeps two devices remembered and logs out only one of them', function (): void {
        $laptop = new FakeCookieJar();
        $phone = new FakeCookieJar();
        ($this->guardFor)($laptop)->login($this->user, remember: true);
        ($this->guardFor)($phone)->login($this->user, remember: true);

        $laptopReturns = ($this->guardFor)($laptop)->user();
        $phoneReturns = ($this->guardFor)($phone)->user();
        ($this->guardFor)($laptop)->logout();

        expect($laptopReturns)->toBe($this->user)
            ->and($phoneReturns)->toBe($this->user)
            ->and($this->connection->rows())->toHaveCount(1)
            ->and(($this->guardFor)($laptop)->user())->toBeNull()
            ->and(($this->guardFor)($phone)->user())->toBe($this->user);
    });

    it('rejects a tampered validator', function (): void {
        $cookieJar = new FakeCookieJar();
        ($this->guardFor)($cookieJar)->login($this->user, remember: true);
        $selector = explode(':', (string) $cookieJar->get('remember_admin'))[0];
        $cookieJar->set('remember_admin', $selector . ':' . str_repeat('f', 64));

        expect(($this->guardFor)($cookieJar)->user())->toBeNull()
            ->and($this->connection->rows())->toHaveCount(1);
    });

    it('deletes a device token that has expired when it is presented', function (): void {
        $cookieJar = new FakeCookieJar();
        ($this->guardFor)($cookieJar)->login($this->user, remember: true);

        $this->clock->travel('+61 minutes');

        expect(($this->guardFor)($cookieJar)->user())->toBeNull()
            ->and($this->connection->rows())->toBe([])
            ->and($this->repository->clearExpiredTokens())->toBe(0);
    });
});
