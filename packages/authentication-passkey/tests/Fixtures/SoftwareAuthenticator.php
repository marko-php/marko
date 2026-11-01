<?php

declare(strict_types=1);

namespace Marko\AuthenticationPasskey\Tests\Fixtures;

use Marko\AuthenticationPasskey\Ceremony\Base64Url;
use OpenSSLAsymmetricKey;
use RuntimeException;

/**
 * A software passkey: an ES256 key pair that answers creation and request
 * options the way a browser and platform authenticator do, producing the
 * PublicKeyCredential JSON (credential.toJSON()) a page posts back.
 *
 * Every field a test may want to tamper with can be overridden per call:
 * origin, type, challenge, rpId (hashed into the authenticator data),
 * signCount, userVerified, userHandle.
 */
class SoftwareAuthenticator
{
    private const int FLAG_USER_PRESENT = 0x01;

    private const int FLAG_USER_VERIFIED = 0x04;

    private const int FLAG_ATTESTED_CREDENTIAL = 0x40;

    public string $credentialId;

    public int $signCount = 0;

    private OpenSSLAsymmetricKey $privateKey;

    public function __construct(
        public string $rpId = 'example.com',
        public string $origin = 'https://example.com',
        ?string $credentialId = null,
    ) {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);

        if ($key === false) {
            throw new RuntimeException('Could not create an EC key: ' . openssl_error_string());
        }

        $this->privateKey = $key;
        $this->credentialId = $credentialId ?? random_bytes(16);
    }

    /**
     * Answer navigator.credentials.create().
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public function register(
        string $challenge,
        array $overrides = [],
    ): array {
        $clientDataJson = $this->clientDataJson('webauthn.create', $challenge, $overrides);
        $authenticatorData = $this->authenticatorData($overrides, attested: true);

        $attestationObject = Cbor::map([
            [Cbor::text('fmt'), Cbor::text('none')],
            [Cbor::text('attStmt'), Cbor::map([])],
            [Cbor::text('authData'), Cbor::bytes($authenticatorData)],
        ]);

        return [
            'id' => Base64Url::encode($this->credentialId),
            'rawId' => Base64Url::encode($this->credentialId),
            'type' => 'public-key',
            'authenticatorAttachment' => 'platform',
            'clientExtensionResults' => [],
            'response' => [
                'clientDataJSON' => Base64Url::encode($clientDataJson),
                'attestationObject' => Base64Url::encode($attestationObject),
                'transports' => ['internal', 'hybrid'],
            ],
        ];
    }

    /**
     * Answer navigator.credentials.get().
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public function assert(
        string $challenge,
        ?string $userHandle,
        array $overrides = [],
    ): array {
        $this->signCount++;
        $clientDataJson = $this->clientDataJson('webauthn.get', $challenge, $overrides);
        $authenticatorData = $this->authenticatorData($overrides, attested: false);

        openssl_sign(
            $authenticatorData . hash('sha256', $clientDataJson, true),
            $signature,
            $this->privateKey,
            OPENSSL_ALGO_SHA256,
        );

        $userHandle = array_key_exists('userHandle', $overrides) ? $overrides['userHandle'] : $userHandle;

        return [
            'id' => Base64Url::encode($this->credentialId),
            'rawId' => Base64Url::encode($this->credentialId),
            'type' => 'public-key',
            'authenticatorAttachment' => 'platform',
            'clientExtensionResults' => [],
            'response' => [
                'clientDataJSON' => Base64Url::encode($clientDataJson),
                'authenticatorData' => Base64Url::encode($authenticatorData),
                'signature' => Base64Url::encode((string) $signature),
                'userHandle' => $userHandle === null ? null : Base64Url::encode($userHandle),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function clientDataJson(
        string $type,
        string $challenge,
        array $overrides,
    ): string {
        return (string) json_encode([
            'type' => $overrides['type'] ?? $type,
            'challenge' => Base64Url::encode($overrides['challenge'] ?? $challenge),
            'origin' => $overrides['origin'] ?? $this->origin,
            'crossOrigin' => $overrides['crossOrigin'] ?? false,
        ], JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function authenticatorData(
        array $overrides,
        bool $attested,
    ): string {
        $flags = self::FLAG_USER_PRESENT;

        if ($overrides['userVerified'] ?? true) {
            $flags |= self::FLAG_USER_VERIFIED;
        }

        $data = hash('sha256', $overrides['rpId'] ?? $this->rpId, true)
            . chr($attested ? $flags | self::FLAG_ATTESTED_CREDENTIAL : $flags)
            . pack('N', $overrides['signCount'] ?? $this->signCount);

        if (!$attested) {
            return $data;
        }

        return $data
            . str_repeat("\0", 16)
            . pack('n', strlen($this->credentialId))
            . $this->credentialId
            . $this->coseKey();
    }

    private function coseKey(): string
    {
        $details = openssl_pkey_get_details($this->privateKey);

        if ($details === false) {
            throw new RuntimeException('Could not read the EC key');
        }

        return Cbor::map([
            [Cbor::int(1), Cbor::int(2)],
            [Cbor::int(3), Cbor::int(-7)],
            [Cbor::int(-1), Cbor::int(1)],
            [Cbor::int(-2), Cbor::bytes(str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT))],
            [Cbor::int(-3), Cbor::bytes(str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT))],
        ]);
    }
}
