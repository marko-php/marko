<?php

declare(strict_types=1);

namespace Marko\Authentication\Tests\Unit\Guard;

use Marko\Authentication\AuthenticatableInterface;
use Marko\Authentication\Contracts\ChallengeableGuardInterface;
use Marko\Authentication\Contracts\LoginChallengeInterface;
use Marko\Authentication\Event\LoginEvent;
use Marko\Authentication\Exceptions\AuthException;
use Marko\Authentication\Guard\SessionGuard;
use Marko\Authentication\Token\RememberTokenManager;
use Marko\Testing\Fake\FakeAuthenticatable;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeCookieJar;
use Marko\Testing\Fake\FakeEventDispatcher;
use Marko\Testing\Fake\FakeSession;
use Marko\Testing\Fake\FakeUserProvider;
use RuntimeException;

/**
 * A challenge that requires itself for the users it lists.
 */
function loginChallenge(
    string $name,
    bool $required = true,
): LoginChallengeInterface {
    return new readonly class ($name, $required) implements LoginChallengeInterface
    {
        public function __construct(
            private string $name,
            private bool $required,
        ) {}

        public function requiresChallenge(
            AuthenticatableInterface $user,
        ): bool {
            return $this->required;
        }

        public function getName(): string
        {
            return $this->name;
        }
    };
}

/**
 * @param list<LoginChallengeInterface> $challenges
 * @return array{guard: SessionGuard, session: FakeSession, events: FakeEventDispatcher, cookies: FakeCookieJar, user: FakeAuthenticatable}
 */
function challengeGuard(
    array $challenges,
): array {
    $session = new FakeSession();
    $session->start();
    $user = new FakeAuthenticatable(id: 42);
    $events = new FakeEventDispatcher();
    $cookies = new FakeCookieJar();

    $guard = new SessionGuard(
        session: $session,
        provider: new FakeUserProvider([42 => $user]),
        name: 'web',
        cookieJar: $cookies,
        tokenManager: new RememberTokenManager(new FakeClock()),
        eventDispatcher: $events,
        challenges: $challenges,
    );

    return ['guard' => $guard, 'session' => $session, 'events' => $events, 'cookies' => $cookies, 'user' => $user];
}

test('SessionGuard is a challengeable guard', function (): void {
    expect(challengeGuard([])['guard'])->toBeInstanceOf(ChallengeableGuardInterface::class);
});

test('a configured challenge blocks a full login until it is completed', function (): void {
    ['guard' => $guard, 'session' => $session, 'user' => $user] = challengeGuard([loginChallenge('two_factor')]);

    $result = $guard->attempt(['email' => 'ada@example.com', 'password' => 'secret']);

    expect($result)->toBeFalse()
        ->and($guard->check())->toBeFalse()
        ->and($session->has('auth_web_user_id'))->toBeFalse()
        ->and($guard->hasPendingChallenge())->toBeTrue()
        ->and($guard->pendingChallengeName())->toBe('two_factor')
        ->and($guard->pendingChallengeUser())->toBe($user)
        ->and($session->regenerated)->toBeTrue();

    $guard->completeChallenge();

    expect($guard->check())->toBeTrue()
        ->and($guard->user())->toBe($user)
        ->and($guard->hasPendingChallenge())->toBeFalse()
        ->and($guard->pendingChallengeUser())->toBeNull();
});

test('no LoginEvent fires before the challenge is completed', function (): void {
    ['guard' => $guard, 'events' => $events] = challengeGuard([loginChallenge('two_factor')]);

    $guard->attempt(['email' => 'ada@example.com', 'password' => 'secret']);

    expect($events->dispatched)->toBeEmpty();

    $guard->completeChallenge(remember: true);

    expect($events->dispatched)->toHaveCount(1)
        ->and($events->dispatched[0])->toBeInstanceOf(LoginEvent::class)
        ->and($events->dispatched[0]->remember)->toBeTrue();
});

test('a remember cookie is only issued when the challenge is completed with remember', function (): void {
    ['guard' => $guard, 'cookies' => $cookies] = challengeGuard([loginChallenge('two_factor')]);

    $guard->attempt(['email' => 'ada@example.com', 'password' => 'secret']);

    expect($cookies->get('remember_web'))->toBeNull();

    $guard->completeChallenge(remember: true);

    expect($cookies->get('remember_web'))->not->toBeNull();
});

test('guards without challenges log in exactly as before', function (): void {
    ['guard' => $guard, 'events' => $events] = challengeGuard([]);

    expect($guard->attempt(['email' => 'ada@example.com', 'password' => 'secret']))->toBeTrue()
        ->and($guard->check())->toBeTrue()
        ->and($guard->hasPendingChallenge())->toBeFalse()
        ->and($events->dispatched)->toHaveCount(1);
});

test('a challenge that does not require itself for the user lets the login through', function (): void {
    ['guard' => $guard] = challengeGuard([loginChallenge('two_factor', required: false)]);

    expect($guard->attempt(['email' => 'ada@example.com', 'password' => 'secret']))->toBeTrue()
        ->and($guard->hasPendingChallenge())->toBeFalse();
});

test('wrong credentials never start a challenge', function (): void {
    $session = new FakeSession();
    $session->start();
    $guard = new SessionGuard(
        session: $session,
        provider: new FakeUserProvider(
            [42 => new FakeAuthenticatable(id: 42)],
            fn (): bool => false,
        ),
        name: 'web',
        challenges: [loginChallenge('two_factor')],
    );

    expect($guard->attempt(['email' => 'ada@example.com', 'password' => 'wrong']))->toBeFalse()
        ->and($guard->hasPendingChallenge())->toBeFalse();
});

test('cancelling clears the challenge session state', function (): void {
    ['guard' => $guard, 'session' => $session] = challengeGuard([loginChallenge('two_factor')]);

    $guard->attempt(['email' => 'ada@example.com', 'password' => 'secret']);
    $guard->cancelChallenge();

    expect($guard->hasPendingChallenge())->toBeFalse()
        ->and($guard->pendingChallengeUser())->toBeNull()
        ->and($guard->pendingChallengeName())->toBeNull()
        ->and($guard->check())->toBeFalse()
        ->and($session->has('auth_web_challenge_user_id'))->toBeFalse()
        ->and($session->has('auth_web_challenge_name'))->toBeFalse();
});

test('completing without a pending challenge throws', function (): void {
    ['guard' => $guard] = challengeGuard([loginChallenge('two_factor')]);

    $guard->completeChallenge();
})->throws(AuthException::class, "Guard 'web' has no pending login challenge to complete");

test('completing one challenge moves on to the next one that requires itself', function (): void {
    ['guard' => $guard, 'events' => $events] = challengeGuard([
        loginChallenge('two_factor'),
        loginChallenge('skipped', required: false),
        loginChallenge('passkey'),
    ]);

    $guard->attempt(['email' => 'ada@example.com', 'password' => 'secret']);
    $guard->completeChallenge();

    expect($guard->pendingChallengeName())->toBe('passkey')
        ->and($guard->check())->toBeFalse()
        ->and($events->dispatched)->toBeEmpty();

    $guard->completeChallenge();

    expect($guard->check())->toBeTrue()
        ->and($events->dispatched)->toHaveCount(1);
});

test('a pending challenge whose user no longer exists is cleared and fails loudly', function (): void {
    $session = new FakeSession();
    $session->start();
    $session->set('auth_web_challenge_user_id', 99);
    $session->set('auth_web_challenge_name', 'two_factor');
    $guard = new SessionGuard(
        session: $session,
        provider: new FakeUserProvider([]),
        name: 'web',
        challenges: [loginChallenge('two_factor')],
    );

    expect($guard->pendingChallengeUser())->toBeNull();

    try {
        $guard->completeChallenge();
    } catch (AuthException) {
        expect($guard->hasPendingChallenge())->toBeFalse();

        return;
    }

    throw new RuntimeException('Expected completeChallenge() to throw');
});

test('logout clears a pending challenge', function (): void {
    ['guard' => $guard] = challengeGuard([loginChallenge('two_factor')]);

    $guard->attempt(['email' => 'ada@example.com', 'password' => 'secret']);
    $guard->logout();

    expect($guard->hasPendingChallenge())->toBeFalse();
});

test('direct login bypasses challenges by design', function (): void {
    ['guard' => $guard, 'user' => $user] = challengeGuard([loginChallenge('two_factor')]);

    $guard->login($user);

    expect($guard->check())->toBeTrue()
        ->and($guard->hasPendingChallenge())->toBeFalse();
});

test('a new attempt replaces an existing login with the pending challenge', function (): void {
    ['guard' => $guard, 'session' => $session, 'user' => $user] = challengeGuard([loginChallenge('two_factor')]);

    $guard->login($user);
    $guard->attempt(['email' => 'ada@example.com', 'password' => 'secret']);

    expect($guard->check())->toBeFalse()
        ->and($session->has('auth_web_user_id'))->toBeFalse()
        ->and($guard->hasPendingChallenge())->toBeTrue();
});
