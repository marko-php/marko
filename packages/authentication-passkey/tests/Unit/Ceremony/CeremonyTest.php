<?php

declare(strict_types=1);

use Marko\AuthenticationPasskey\Ceremony\AssertionResponse;
use Marko\AuthenticationPasskey\Ceremony\AttestationResponse;
use Marko\AuthenticationPasskey\Ceremony\Base64Url;
use Marko\AuthenticationPasskey\Ceremony\PasskeyChallengeStore;
use Marko\AuthenticationPasskey\Event\PasskeyFailureReason;
use Marko\AuthenticationPasskey\Exceptions\PasskeyVerificationException;
use Marko\AuthenticationPasskey\Tests\Fixtures\PasskeyHarness;
use Marko\AuthenticationPasskey\Tests\Fixtures\SoftwareAuthenticator;

it('round-trips base64url and rejects other alphabets', function (): void {
    $bytes = "\xfb\xff\x00binary";

    expect(Base64Url::decode(Base64Url::encode($bytes)))->toBe($bytes)
        ->and(Base64Url::encode($bytes))->not->toContain('=', '+', '/')
        ->and(Base64Url::decode('ab+/'))->toBeNull()
        ->and(Base64Url::decode('a'))->toBeNull();
});

it('issues a challenge that can be consumed once', function (): void {
    $harness = new PasskeyHarness();
    $store = $harness->challengeStore();
    $challenge = $store->issue(PasskeyChallengeStore::AUTHENTICATION);

    expect($store->consume(PasskeyChallengeStore::AUTHENTICATION))->toBe($challenge)
        ->and(fn () => $store->consume(PasskeyChallengeStore::AUTHENTICATION))->toThrow(
            PasskeyVerificationException::class,
        );
});

it('keeps registration and authentication challenges apart', function (): void {
    $store = new PasskeyHarness()->challengeStore();
    $store->issue(PasskeyChallengeStore::REGISTRATION);

    expect(fn () => $store->consume(PasskeyChallengeStore::AUTHENTICATION))->toThrow(
        PasskeyVerificationException::class,
    );
});

it('expires a challenge after timeout_seconds and removes it either way', function (): void {
    $harness = new PasskeyHarness();
    $store = $harness->challengeStore();
    $store->issue(PasskeyChallengeStore::AUTHENTICATION);
    $harness->clock->travel('+301 seconds');

    expect(fn () => $store->consume(PasskeyChallengeStore::AUTHENTICATION))
        ->toThrow(PasskeyVerificationException::class, 'could not be verified')
        ->and($harness->session->get('passkey.challenge.authentication'))->toBeNull();
});

it('rejects malformed credential JSON with a MalformedResponse reason', function (array $credential): void {
    try {
        AssertionResponse::fromArray($credential);
        $this->fail('Expected a PasskeyVerificationException');
    } catch (PasskeyVerificationException $exception) {
        expect($exception->getReason())->toBe(PasskeyFailureReason::MalformedResponse)
            ->and($exception->getStatusCode())->toBe(400);
    }
})->with(function (): array {
    $valid = new SoftwareAuthenticator()->assert(random_bytes(32), null);

    return [
        'wrong type' => [[...$valid, 'type' => 'password']],
        'missing rawId' => [array_diff_key($valid, ['rawId' => true])],
        'id differs from rawId' => [[...$valid, 'id' => 'other']],
        'rawId not base64url' => [[...$valid, 'id' => 'a+b', 'rawId' => 'a+b']],
        'rawId too long' => [[...$valid, 'id' => str_repeat('a', 516), 'rawId' => str_repeat('a', 516)]],
        'response missing' => [[...$valid, 'response' => null]],
        'signature missing' => [[...$valid, 'response' => [...$valid['response'], 'signature' => null]]],
        'client data not JSON' => [[...$valid, 'response' => [...$valid['response'], 'clientDataJSON' => Base64Url::encode(
            'nope',
        )]]],
        'client data challenge not base64url' => [[...$valid, 'response' => [...$valid['response'], 'clientDataJSON' => Base64Url::encode(
            '{"type":"webauthn.get","challenge":"+","origin":"https://example.com"}',
        )]]],
    ];
});

it('rejects an attestation object that is not CBOR', function (): void {
    $harness = new PasskeyHarness();
    $challenge = random_bytes(32);
    $credential = new SoftwareAuthenticator()->register($challenge);
    $credential['response']['attestationObject'] = Base64Url::encode(random_bytes(40));

    try {
        $harness->verifier->verifyAttestation(AttestationResponse::fromArray($credential), $challenge);
        $this->fail('Expected a PasskeyVerificationException');
    } catch (PasskeyVerificationException $exception) {
        expect($exception->getReason())->toBe(PasskeyFailureReason::MalformedResponse);
    }
});

it('rejects transports that are not a list of names', function (): void {
    $credential = new SoftwareAuthenticator()->register(random_bytes(32));
    $credential['response']['transports'] = ['usb', 7];

    expect(fn () => AttestationResponse::fromArray($credential))->toThrow(PasskeyVerificationException::class);
});
