<?php

declare(strict_types=1);

use Marko\Authentication\Contracts\ChallengeableGuardInterface;
use Marko\Authentication\Contracts\PasswordConfirmationInterface;
use Marko\Authentication\Contracts\StatelessGuardInterface;
use Marko\Authentication\Event\LoginEvent;
use Marko\AuthenticationPasskey\Ceremony\PasskeyUserEntity;
use Marko\AuthenticationPasskey\Event\PasskeyFailureReason;
use Marko\AuthenticationPasskey\Event\PasskeyLoginFailedEvent;
use Marko\AuthenticationPasskey\Exceptions\PasskeyAuthenticationFailedException;
use Marko\AuthenticationPasskey\Exceptions\PasskeyConfigurationException;
use Marko\AuthenticationPasskey\Exceptions\TooManyPasskeyAttemptsException;
use Marko\AuthenticationPasskey\Tests\Fixtures\AlwaysRequiredChallenge;
use Marko\AuthenticationPasskey\Tests\Fixtures\PasskeyHarness;
use Marko\AuthenticationPasskey\Tests\Fixtures\SoftwareAuthenticator;
use Marko\Testing\Fake\FakeGuard;

/**
 * Run a sign-in: fresh request options, the authenticator's answer (with any
 * overrides), then authenticate(). Returns the failure reason, or null on success.
 *
 * @param array<string, mixed> $overrides
 */
function passkeySignIn(
    PasskeyHarness $harness,
    SoftwareAuthenticator $authenticator,
    array $overrides = [],
    string|false|null $userHandle = null,
): ?PasskeyFailureReason {
    $options = $harness->authentication()->requestOptions();
    $handle = $userHandle === false ? null : ($userHandle ?? PasskeyUserEntity::handleFor('users', 7));

    try {
        $harness->authentication()->authenticate(
            $authenticator->assert(PasskeyHarness::challengeOf($options), $handle, $overrides),
        );
    } catch (PasskeyAuthenticationFailedException $exception) {
        return $exception->getReason();
    }

    return null;
}

beforeEach(function (): void {
    $this->harness = new PasskeyHarness();
    $this->authenticator = new SoftwareAuthenticator();
    $this->passkey = $this->harness->registerPasskey($this->authenticator, $this->harness->ada);
});

it('signs the owner in with a discoverable passkey and empty allow list', function (): void {
    $options = $this->harness->authentication()->requestOptions();

    $user = $this->harness->authentication()->authenticate(
        $this->authenticator->assert(PasskeyHarness::challengeOf($options), PasskeyUserEntity::handleFor('users', 7)),
    );

    expect($options['allowCredentials'])->toBe([])
        ->and($user)->toBe($this->harness->ada)
        ->and($this->harness->guard()->user())->toBe($this->harness->ada)
        ->and($this->harness->eventDispatcher->dispatched(LoginEvent::class))->toHaveCount(1);
});

it('signs in when the authenticator sends no user handle', function (): void {
    expect(passkeySignIn($this->harness, $this->authenticator, userHandle: false))->toBeNull()
        ->and($this->harness->guard()->id())->toBe(7);
});

it('allows only the user\'s passkeys in request options for a known user', function (): void {
    $options = $this->harness->authentication()->requestOptions($this->harness->ada);

    expect($options['allowCredentials'])->toBe([
        ['type' => 'public-key', 'id' => $this->passkey->credentialId, 'transports' => ['internal', 'hybrid']],
    ]);
});

it('stores the new signature counter and the time of use', function (): void {
    $this->harness->clock->travel('+60 seconds');
    passkeySignIn($this->harness, $this->authenticator);

    $stored = $this->harness->repository->findByCredentialId($this->passkey->credentialId);

    expect($stored->signCount)->toBe(1)
        ->and($stored->lastUsedAt?->format('Y-m-d H:i:s'))->toBe('2026-06-01 12:01:00');
});

it('stamps password confirmation after the login, which clears any earlier stamp', function (): void {
    $this->harness->session->set(PasswordConfirmationInterface::SESSION_KEY, 1);
    $this->harness->clock->travel('+30 seconds');

    passkeySignIn($this->harness, $this->authenticator);

    expect($this->harness->session->get(PasswordConfirmationInterface::SESSION_KEY))
        ->toBe($this->harness->clock->now()->getTimestamp())
        ->and($this->harness->passwordConfirmation()->isRecentlyConfirmed())->toBeTrue();
});

it('skips the guard\'s login challenges, which a password login of the same user must pass', function (): void {
    $harness = new PasskeyHarness([
        'authentication.guards' => [
            'session' => ['driver' => 'session', 'provider' => 'users', 'challenges' => [AlwaysRequiredChallenge::class]],
        ],
    ]);
    $authenticator = new SoftwareAuthenticator();
    $harness->registerPasskey($authenticator, $harness->ada);
    $guard = $harness->guard();

    expect($guard)->toBeInstanceOf(ChallengeableGuardInterface::class)
        ->and(passkeySignIn($harness, $authenticator))->toBeNull()
        ->and($guard->user())->toBe($harness->ada)
        ->and($guard->hasPendingChallenge())->toBeFalse()
        ->and($harness->eventDispatcher->dispatched(LoginEvent::class))->toHaveCount(1);
});

it('cancels a challenge left pending by an abandoned password login', function (): void {
    $harness = new PasskeyHarness([
        'authentication.guards' => [
            'session' => ['driver' => 'session', 'provider' => 'users', 'challenges' => [AlwaysRequiredChallenge::class]],
        ],
    ]);
    $authenticator = new SoftwareAuthenticator();
    $harness->registerPasskey($authenticator, $harness->ada);
    $guard = $harness->guard();
    $harness->session->set('auth_session_challenge_user_id', 8);
    $harness->session->set('auth_session_challenge_name', 'two_factor');

    expect($guard->hasPendingChallenge())->toBeTrue()
        ->and(passkeySignIn($harness, $authenticator))->toBeNull()
        ->and($guard->hasPendingChallenge())->toBeFalse()
        ->and($guard->pendingChallengeName())->toBeNull()
        ->and($guard->id())->toBe(7);
});

it('rejects a replayed response because its challenge was already used', function (): void {
    $options = $this->harness->authentication()->requestOptions();
    $response = $this->authenticator->assert(
        PasskeyHarness::challengeOf($options),
        PasskeyUserEntity::handleFor('users', 7),
    );
    $this->harness->authentication()->authenticate($response);
    $this->harness->guard()->logout();

    expect(fn () => $this->harness->authentication()->authenticate($response))
        ->toThrow(PasskeyAuthenticationFailedException::class);
    expect($this->harness->eventDispatcher->dispatched(PasskeyLoginFailedEvent::class)[0]->reason)
        ->toBe(PasskeyFailureReason::ChallengeMismatch);
});

it('rejects a response that signs a different challenge', function (): void {
    expect(passkeySignIn($this->harness, $this->authenticator, ['challenge' => random_bytes(32)]))
        ->toBe(PasskeyFailureReason::ChallengeMismatch)
        ->and($this->harness->guard()->check())->toBeFalse();
});

it('rejects a response after the challenge timed out', function (): void {
    $options = $this->harness->authentication()->requestOptions();
    $this->harness->clock->travel('+301 seconds');

    expect(fn () => $this->harness->authentication()->authenticate(
        $this->authenticator->assert(PasskeyHarness::challengeOf($options), null),
    ))->toThrow(PasskeyAuthenticationFailedException::class);
});

it('rejects a signature counter that does not increase', function (): void {
    passkeySignIn($this->harness, $this->authenticator);
    $this->harness->guard()->logout();

    expect(passkeySignIn($this->harness, $this->authenticator, ['signCount' => 1]))
        ->toBe(PasskeyFailureReason::SignCountRegressed);
});

it('accepts a counter of 0 from an authenticator that keeps none', function (): void {
    expect(passkeySignIn($this->harness, $this->authenticator, ['signCount' => 0]))->toBeNull();
    $this->harness->guard()->logout();

    expect(passkeySignIn($this->harness, $this->authenticator, ['signCount' => 0]))->toBeNull();
});

it('rejects a counter of 0 once the authenticator has reported a counter', function (): void {
    passkeySignIn($this->harness, $this->authenticator, ['signCount' => 5]);
    $this->harness->guard()->logout();

    expect(passkeySignIn($this->harness, $this->authenticator, ['signCount' => 0]))
        ->toBe(PasskeyFailureReason::SignCountRegressed);
});

it('rejects a response from an origin that is not allowed', function (): void {
    expect(passkeySignIn($this->harness, $this->authenticator, ['origin' => 'https://evil.example']))
        ->toBe(PasskeyFailureReason::OriginMismatch)
        ->and(passkeySignIn($this->harness, $this->authenticator, ['origin' => 'https://sub.example.com']))
        ->toBe(PasskeyFailureReason::OriginMismatch)
        ->and(passkeySignIn($this->harness, $this->authenticator, ['crossOrigin' => true]))
        ->toBe(PasskeyFailureReason::OriginMismatch);
});

it('rejects a response made for another relying party id', function (): void {
    expect(passkeySignIn($this->harness, $this->authenticator, ['rpId' => 'evil.example']))
        ->toBe(PasskeyFailureReason::RelyingPartyMismatch);
});

it('rejects an unknown credential', function (): void {
    expect(passkeySignIn($this->harness, new SoftwareAuthenticator()))->toBe(PasskeyFailureReason::UnknownCredential);
});

it('rejects a user handle that belongs to someone else', function (): void {
    expect(passkeySignIn($this->harness, $this->authenticator, userHandle: PasskeyUserEntity::handleFor('users', 8)))
        ->toBe(PasskeyFailureReason::UserHandleMismatch);
});

it('rejects a passkey whose owner no longer exists', function (): void {
    $this->passkey->userId = '99';
    $this->harness->repository->save($this->passkey);

    expect(passkeySignIn($this->harness, $this->authenticator, userHandle: false))->toBe(
        PasskeyFailureReason::UnknownUser,
    );
});

it('rejects a malformed response', function (): void {
    $this->harness->authentication()->requestOptions();

    expect(fn () => $this->harness->authentication()->authenticate(['type' => 'public-key']))
        ->toThrow(PasskeyAuthenticationFailedException::class);
});

it('dispatches PasskeyLoginFailedEvent with the reason, credential id and IP, and a generic 401', function (): void {
    $options = $this->harness->authentication()->requestOptions();

    try {
        $this->harness->authentication()->authenticate(
            $this->authenticator->assert(
                PasskeyHarness::challengeOf($options),
                null,
                ['origin' => 'https://evil.example'],
            ),
        );
    } catch (PasskeyAuthenticationFailedException $exception) {
    }

    $event = $this->harness->eventDispatcher->dispatched(PasskeyLoginFailedEvent::class)[0];

    expect($exception->getStatusCode())->toBe(401)
        ->and($exception->getMessage())->toBe('Passkey sign-in failed.')
        ->and($event->guard)->toBe('session')
        ->and($event->reason)->toBe(PasskeyFailureReason::OriginMismatch)
        ->and($event->credentialId)->toBe($this->passkey->credentialId)
        ->and($event->ipAddress)->toBe('203.0.113.9');
});

it('throttles failed assertions per client and resets the count on success', function (): void {
    foreach (range(1, 4) as $ignored) {
        passkeySignIn($this->harness, $this->authenticator, ['origin' => 'https://evil.example']);
    }

    expect(passkeySignIn($this->harness, $this->authenticator))->toBeNull();
    $this->harness->guard()->logout();

    foreach (range(1, 5) as $ignored) {
        passkeySignIn($this->harness, $this->authenticator, ['origin' => 'https://evil.example']);
    }

    try {
        passkeySignIn($this->harness, $this->authenticator);
        $this->fail('Expected a lockout');
    } catch (TooManyPasskeyAttemptsException $exception) {
        expect($exception->getStatusCode())->toBe(429)
            ->and($exception->getHeaders()['Retry-After'])->toBe((string) $exception->getRetryAfter());
    }
});

it('confirms the signed-in user without logging in again', function (): void {
    $this->harness->guard()->login($this->harness->ada);
    $this->harness->eventDispatcher->clear();
    $options = $this->harness->authentication()->requestOptions($this->harness->ada);

    $this->harness->authentication()->confirm(
        $this->authenticator->assert(PasskeyHarness::challengeOf($options), PasskeyUserEntity::handleFor('users', 7)),
    );

    expect($this->harness->passwordConfirmation()->isRecentlyConfirmed())->toBeTrue()
        ->and($this->harness->eventDispatcher->dispatched(LoginEvent::class))->toBe([]);
});

it('refuses to confirm with another user\'s passkey', function (): void {
    $this->harness->guard()->login($this->harness->grace);
    $options = $this->harness->authentication()->requestOptions();

    expect(fn () => $this->harness->authentication()->confirm(
        $this->authenticator->assert(PasskeyHarness::challengeOf($options), null),
    ))->toThrow(PasskeyAuthenticationFailedException::class);
    expect($this->harness->passwordConfirmation()->isRecentlyConfirmed())->toBeFalse();
});

it('throws a configuration error for a stateless guard', function (): void {
    $this->harness->authManager->useGuard('session', new class () extends FakeGuard implements StatelessGuardInterface
    {
        public function getChallenge(): string
        {
            return 'Bearer';
        }
    });

    expect(fn () => passkeySignIn($this->harness, $this->authenticator))
        ->toThrow(
            PasskeyConfigurationException::class,
            "Passkeys need a session guard, but guard 'test' is stateless",
        );
});
