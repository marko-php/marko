<?php

declare(strict_types=1);

use Marko\Authentication\Contracts\StatelessGuardInterface;
use Marko\Authentication\Exceptions\UnauthenticatedException;
use Marko\AuthenticationPasskey\Ceremony\Base64Url;
use Marko\AuthenticationPasskey\Ceremony\PasskeyUserEntity;
use Marko\AuthenticationPasskey\Event\PasskeyFailureReason;
use Marko\AuthenticationPasskey\Event\PasskeyRegisteredEvent;
use Marko\AuthenticationPasskey\Exceptions\InvalidPasskeyNameException;
use Marko\AuthenticationPasskey\Exceptions\PasskeyConfigurationException;
use Marko\AuthenticationPasskey\Exceptions\PasskeyVerificationException;
use Marko\AuthenticationPasskey\Tests\Fixtures\PasskeyHarness;
use Marko\AuthenticationPasskey\Tests\Fixtures\SoftwareAuthenticator;
use Marko\Testing\Fake\FakeGuard;

function registrationReason(
    callable $callback,
): ?PasskeyFailureReason {
    try {
        $callback();
    } catch (PasskeyVerificationException $exception) {
        return $exception->getReason();
    }

    return null;
}

beforeEach(function (): void {
    $this->harness = new PasskeyHarness();
    $this->authenticator = new SoftwareAuthenticator();
    $this->harness->guard()->login($this->harness->ada);
});

it('builds creation options for the signed-in user with an opaque user handle', function (): void {
    $options = $this->harness->registration()->creationOptions('ada@example.com', 'Ada Lovelace');

    expect($options['user'])->toBe([
        'id' => Base64Url::encode(PasskeyUserEntity::handleFor('users', 7)),
        'name' => 'ada@example.com',
        'displayName' => 'Ada Lovelace',
    ])
        ->and(strlen(PasskeyHarness::challengeOf($options)))->toBe(32)
        ->and($options['excludeCredentials'])->toBe([]);
});

it('stores a verified passkey for the signed-in user and dispatches PasskeyRegisteredEvent', function (): void {
    $options = $this->harness->registration()->creationOptions('ada@example.com', 'Ada');

    $passkey = $this->harness->registration()->register(
        $this->authenticator->register(PasskeyHarness::challengeOf($options)),
        '  MacBook Touch ID ',
    );

    $row = $this->harness->connection->rows()[0];
    $event = $this->harness->eventDispatcher->dispatched(PasskeyRegisteredEvent::class)[0];

    expect($passkey->credentialId)->toBe(Base64Url::encode($this->authenticator->credentialId))
        ->and($row['provider'])->toBe('users')
        ->and($row['user_id'])->toBe('7')
        ->and($row['name'])->toBe('MacBook Touch ID')
        ->and($row['public_key'])->toStartWith('-----BEGIN PUBLIC KEY-----')
        ->and((int) $row['sign_count'])->toBe(0)
        ->and(json_decode($row['transports'], true))->toBe(['internal', 'hybrid'])
        ->and($row['created_at'])->toBe('2026-06-01 12:00:00')
        ->and($row['last_used_at'])->toBeNull()
        ->and($event->user)->toBe($this->harness->ada)
        ->and($event->credential)->toBe($passkey)
        ->and($event->guard)->toBe('session');
});

it('excludes the user\'s existing passkeys from new creation options', function (): void {
    $passkey = $this->harness->registerPasskey($this->authenticator, $this->harness->ada);
    $this->harness->guard()->login($this->harness->ada);

    $options = $this->harness->registration()->creationOptions('ada@example.com', 'Ada');

    expect(array_column($options['excludeCredentials'], 'id'))->toBe([$passkey->credentialId]);
});

it('rejects a response to a challenge that was never issued', function (): void {
    expect(registrationReason(fn () => $this->harness->registration()->register(
        $this->authenticator->register(random_bytes(32)),
        'Laptop',
    )))->toBe(PasskeyFailureReason::ChallengeMismatch);
});

it('rejects a response that signs a different challenge than the issued one', function (): void {
    $this->harness->registration()->creationOptions('ada@example.com', 'Ada');

    expect(registrationReason(fn () => $this->harness->registration()->register(
        $this->authenticator->register(random_bytes(32)),
        'Laptop',
    )))->toBe(PasskeyFailureReason::ChallengeMismatch);
});

it('rejects a replayed registration response', function (): void {
    $options = $this->harness->registration()->creationOptions('ada@example.com', 'Ada');
    $response = $this->authenticator->register(PasskeyHarness::challengeOf($options));
    $this->harness->registration()->register($response, 'Laptop');

    expect(registrationReason(fn () => $this->harness->registration()->register($response, 'Laptop')))
        ->toBe(PasskeyFailureReason::ChallengeMismatch);
});

it('rejects a challenge issued while another user was signed in', function (): void {
    $options = $this->harness->registration()->creationOptions('ada@example.com', 'Ada');
    $this->harness->guard()->login($this->harness->grace);

    expect(registrationReason(fn () => $this->harness->registration()->register(
        $this->authenticator->register(PasskeyHarness::challengeOf($options)),
        'Laptop',
    )))->toBe(PasskeyFailureReason::ChallengeMismatch);
});

it('rejects a registration from an origin that is not allowed', function (): void {
    $options = $this->harness->registration()->creationOptions('ada@example.com', 'Ada');

    expect(registrationReason(fn () => $this->harness->registration()->register(
        $this->authenticator->register(PasskeyHarness::challengeOf($options), ['origin' => 'https://evil.example']),
        'Laptop',
    )))->toBe(PasskeyFailureReason::OriginMismatch);
});

it('rejects a registration for another relying party id', function (): void {
    $options = $this->harness->registration()->creationOptions('ada@example.com', 'Ada');

    expect(registrationReason(fn () => $this->harness->registration()->register(
        $this->authenticator->register(PasskeyHarness::challengeOf($options), ['rpId' => 'evil.example']),
        'Laptop',
    )))->toBe(PasskeyFailureReason::RelyingPartyMismatch);
});

it('rejects a get response sent to registration', function (): void {
    $options = $this->harness->registration()->creationOptions('ada@example.com', 'Ada');

    expect(registrationReason(fn () => $this->harness->registration()->register(
        $this->authenticator->register(PasskeyHarness::challengeOf($options), ['type' => 'webauthn.get']),
        'Laptop',
    )))->toBe(PasskeyFailureReason::MalformedResponse);
});

it('rejects a credential id that is already registered', function (): void {
    $this->harness->registerPasskey($this->authenticator, $this->harness->grace);
    $this->harness->guard()->login($this->harness->ada);
    $options = $this->harness->registration()->creationOptions('ada@example.com', 'Ada');

    expect(registrationReason(fn () => $this->harness->registration()->register(
        $this->authenticator->register(PasskeyHarness::challengeOf($options)),
        'Laptop',
    )))->toBe(PasskeyFailureReason::CredentialAlreadyRegistered)
        ->and($this->harness->connection->rows())->toHaveCount(1);
});

it('rejects an empty or overlong name before using the challenge', function (): void {
    $options = $this->harness->registration()->creationOptions('ada@example.com', 'Ada');
    $response = $this->authenticator->register(PasskeyHarness::challengeOf($options));

    expect(fn () => $this->harness->registration()->register($response, '   '))->toThrow(
        InvalidPasskeyNameException::class,
    )
        ->and(fn () => $this->harness->registration()->register($response, str_repeat('a', 256)))->toThrow(
            InvalidPasskeyNameException::class,
        )
        ->and($this->harness->registration()->register($response, 'Laptop')->name)->toBe('Laptop');
});

it('needs a signed-in user', function (): void {
    $this->harness->guard()->logout();

    expect(fn () => $this->harness->registration()->creationOptions('ada@example.com', 'Ada'))
        ->toThrow(UnauthenticatedException::class);
});

it('throws a configuration error for a stateless guard', function (): void {
    $this->harness->authManager->useGuard('session', new class () extends FakeGuard implements StatelessGuardInterface
    {
        public function getChallenge(): string
        {
            return 'Bearer';
        }
    });

    expect(fn () => $this->harness->registration()->creationOptions('ada@example.com', 'Ada'))
        ->toThrow(PasskeyConfigurationException::class, 'Passkeys need a session guard');
});

it('throws a configuration error when the guard names no user provider', function (): void {
    $harness = new PasskeyHarness([
        'authentication.default.provider' => null,
        'authentication.guards' => ['session' => ['driver' => 'session']],
        'authentication.providers' => null,
    ]);
    $harness->guard()->login($harness->ada);

    expect(fn () => $harness->registration()->creationOptions('ada@example.com', 'Ada'))
        ->toThrow(PasskeyConfigurationException::class, "Guard 'session' names no user provider");
});
