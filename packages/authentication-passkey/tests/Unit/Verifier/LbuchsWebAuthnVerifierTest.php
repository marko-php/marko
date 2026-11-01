<?php

declare(strict_types=1);

use Marko\AuthenticationPasskey\Ceremony\AssertionResponse;
use Marko\AuthenticationPasskey\Ceremony\AttestationResponse;
use Marko\AuthenticationPasskey\Ceremony\Base64Url;
use Marko\AuthenticationPasskey\Ceremony\PasskeyUserEntity;
use Marko\AuthenticationPasskey\Config\PasskeyConfig;
use Marko\AuthenticationPasskey\Contracts\PasskeyVerifierInterface;
use Marko\AuthenticationPasskey\Entity\PasskeyCredential;
use Marko\AuthenticationPasskey\Event\PasskeyFailureReason;
use Marko\AuthenticationPasskey\Exceptions\PasskeyVerificationException;
use Marko\AuthenticationPasskey\Tests\Fixtures\PasskeyHarness;
use Marko\AuthenticationPasskey\Tests\Fixtures\SoftwareAuthenticator;
use Marko\AuthenticationPasskey\Verifier\LbuchsWebAuthnVerifier;
use Marko\Testing\Fake\FakeConfigRepository;

function lbuchsVerifier(
    array $config = [],
): LbuchsWebAuthnVerifier {
    return new LbuchsWebAuthnVerifier(new PasskeyConfig(new FakeConfigRepository([
        ...PasskeyHarness::PASSKEY_CONFIG,
        ...$config,
    ])));
}

/**
 * @return array<string, mixed>
 */
function recordedCeremony(): array
{
    return json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/Fixtures/recorded-ceremony.json'), true);
}

function storedCredential(
    string $credentialId,
    string $publicKey,
    int $signCount = 0,
): PasskeyCredential {
    $credential = new PasskeyCredential();
    $credential->credentialId = $credentialId;
    $credential->provider = 'users';
    $credential->userId = '7';
    $credential->name = 'Laptop';
    $credential->publicKey = $publicKey;
    $credential->signCount = $signCount;
    $credential->transports = ['internal'];
    $credential->createdAt = new DateTimeImmutable('2026-06-01 12:00:00');

    return $credential;
}

function verificationReason(
    callable $callback,
): ?PasskeyFailureReason {
    try {
        $callback();
    } catch (PasskeyVerificationException $exception) {
        return $exception->getReason();
    }

    return null;
}

it('implements PasskeyVerifierInterface and is the module binding', function (): void {
    $module = require dirname(__DIR__, 3) . '/module.php';

    expect(lbuchsVerifier())->toBeInstanceOf(PasskeyVerifierInterface::class)
        ->and($module['bindings'][PasskeyVerifierInterface::class])->toBe(LbuchsWebAuthnVerifier::class);
});

it('verifies a recorded registration and then a recorded assertion by the same passkey', function (): void {
    $recorded = recordedCeremony();
    $verifier = lbuchsVerifier();

    $attestation = $verifier->verifyAttestation(
        AttestationResponse::fromArray($recorded['registration']['credential']),
        Base64Url::decode($recorded['registration']['challenge']),
    );

    expect($attestation->credentialId)->toBe($recorded['registration']['credential']['rawId'])
        ->and($attestation->publicKey)->toStartWith('-----BEGIN PUBLIC KEY-----')
        ->and($attestation->signCount)->toBe(0);

    $assertion = $verifier->verifyAssertion(
        AssertionResponse::fromArray($recorded['assertion']['credential']),
        storedCredential($attestation->credentialId, $attestation->publicKey),
        Base64Url::decode($recorded['assertion']['challenge']),
    );

    expect($assertion->signCount)->toBe($recorded['assertion']['sign_count']);
});

it('rejects a recorded assertion checked against another challenge', function (): void {
    $recorded = recordedCeremony();
    $verifier = lbuchsVerifier();
    $attestation = $verifier->verifyAttestation(
        AttestationResponse::fromArray($recorded['registration']['credential']),
        Base64Url::decode($recorded['registration']['challenge']),
    );

    $reason = verificationReason(fn () => $verifier->verifyAssertion(
        AssertionResponse::fromArray($recorded['assertion']['credential']),
        storedCredential($attestation->credentialId, $attestation->publicKey),
        random_bytes(32),
    ));

    expect($reason)->toBe(PasskeyFailureReason::ChallengeMismatch);
});

it('rejects an assertion signed by a different key than the stored one', function (): void {
    $registered = new SoftwareAuthenticator();
    $impostor = new SoftwareAuthenticator(credentialId: $registered->credentialId);
    $verifier = lbuchsVerifier();
    $challenge = random_bytes(32);
    $attestation = $verifier->verifyAttestation(
        AttestationResponse::fromArray($registered->register($challenge)),
        $challenge,
    );

    $reason = verificationReason(fn () => $verifier->verifyAssertion(
        AssertionResponse::fromArray($impostor->assert($challenge, null)),
        storedCredential($attestation->credentialId, $attestation->publicKey),
        $challenge,
    ));

    expect($reason)->toBe(PasskeyFailureReason::InvalidSignature);
});

it('rejects authenticator data made for another relying party id', function (): void {
    $authenticator = new SoftwareAuthenticator();
    $verifier = lbuchsVerifier();
    $challenge = random_bytes(32);

    $registrationReason = verificationReason(fn () => $verifier->verifyAttestation(
        AttestationResponse::fromArray($authenticator->register($challenge, ['rpId' => 'evil.example'])),
        $challenge,
    ));

    $attestation = $verifier->verifyAttestation(
        AttestationResponse::fromArray($authenticator->register($challenge)),
        $challenge,
    );
    $assertionReason = verificationReason(fn () => $verifier->verifyAssertion(
        AssertionResponse::fromArray($authenticator->assert($challenge, null, ['rpId' => 'evil.example'])),
        storedCredential($attestation->credentialId, $attestation->publicKey),
        $challenge,
    ));

    expect($registrationReason)->toBe(PasskeyFailureReason::RelyingPartyMismatch)
        ->and($assertionReason)->toBe(PasskeyFailureReason::RelyingPartyMismatch);
});

it('requires user verification for registration and sign-in', function (): void {
    $authenticator = new SoftwareAuthenticator();
    $verifier = lbuchsVerifier();
    $challenge = random_bytes(32);

    $registrationReason = verificationReason(fn () => $verifier->verifyAttestation(
        AttestationResponse::fromArray($authenticator->register($challenge, ['userVerified' => false])),
        $challenge,
    ));

    $attestation = $verifier->verifyAttestation(
        AttestationResponse::fromArray($authenticator->register($challenge)),
        $challenge,
    );
    $assertionReason = verificationReason(fn () => $verifier->verifyAssertion(
        AssertionResponse::fromArray($authenticator->assert($challenge, null, ['userVerified' => false])),
        storedCredential($attestation->credentialId, $attestation->publicKey),
        $challenge,
    ));

    expect($registrationReason)->toBe(PasskeyFailureReason::UserNotVerified)
        ->and($assertionReason)->toBe(PasskeyFailureReason::UserNotVerified);
});

it('rejects an attestation whose rawId differs from the attested credential id', function (): void {
    $authenticator = new SoftwareAuthenticator();
    $challenge = random_bytes(32);
    $credential = $authenticator->register($challenge);
    $credential['id'] = $credential['rawId'] = Base64Url::encode(random_bytes(16));

    $reason = verificationReason(
        fn () => lbuchsVerifier()->verifyAttestation(AttestationResponse::fromArray($credential), $challenge),
    );

    expect($reason)->toBe(PasskeyFailureReason::MalformedResponse);
});

it('builds creation options for a discoverable, user-verified passkey without attestation', function (): void {
    $existing = storedCredential('abc', 'pem');
    $options = lbuchsVerifier()->creationOptions(
        new PasskeyUserEntity(handle: 'handle-bytes', name: 'ada@example.com', displayName: 'Ada'),
        'challenge-bytes',
        [$existing],
    );

    expect($options)->toBe([
        'rp' => ['id' => 'example.com', 'name' => 'Example'],
        'user' => ['id' => Base64Url::encode('handle-bytes'), 'name' => 'ada@example.com', 'displayName' => 'Ada'],
        'challenge' => Base64Url::encode('challenge-bytes'),
        'pubKeyCredParams' => [
            ['type' => 'public-key', 'alg' => -7],
            ['type' => 'public-key', 'alg' => -8],
            ['type' => 'public-key', 'alg' => -257],
        ],
        'timeout' => 300000,
        'excludeCredentials' => [['type' => 'public-key', 'id' => 'abc', 'transports' => ['internal']]],
        'authenticatorSelection' => [
            'residentKey' => 'required',
            'requireResidentKey' => true,
            'userVerification' => 'required',
        ],
        'attestation' => 'none',
    ]);
});

it('builds request options with an empty allow list for a discoverable sign-in', function (): void {
    expect(lbuchsVerifier()->requestOptions('challenge-bytes', []))->toBe([
        'rpId' => 'example.com',
        'challenge' => Base64Url::encode('challenge-bytes'),
        'timeout' => 300000,
        'allowCredentials' => [],
        'userVerification' => 'required',
    ]);
});
