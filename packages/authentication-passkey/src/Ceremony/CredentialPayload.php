<?php

declare(strict_types=1);

namespace Marko\AuthenticationPasskey\Ceremony;

use Marko\AuthenticationPasskey\Event\PasskeyFailureReason;
use Marko\AuthenticationPasskey\Exceptions\PasskeyVerificationException;

/**
 * Reads the JSON form of a PublicKeyCredential (what credential.toJSON()
 * returns in the browser) and rejects anything that is not one. Shared by
 * AttestationResponse and AssertionResponse.
 */
readonly class CredentialPayload
{
    /**
     * Longest accepted credential id, in base64url characters (384 bytes).
     * The passkey_credentials.credential_id column holds this many.
     */
    public const int MAX_CREDENTIAL_ID_LENGTH = 512;

    /**
     * @param array<string, mixed> $response
     */
    private function __construct(
        public string $credentialId,
        private array $response,
    ) {}

    /**
     * @param array<mixed> $credential
     * @throws PasskeyVerificationException
     */
    public static function fromArray(
        array $credential,
    ): self {
        $rawId = $credential['rawId'] ?? null;
        $response = $credential['response'] ?? null;

        if (($credential['type'] ?? null) !== 'public-key') {
            throw self::malformed('type must be "public-key"');
        }

        if (!is_string($rawId) || $rawId === '' || Base64Url::decode($rawId) === null) {
            throw self::malformed('rawId must be a non-empty base64url string');
        }

        if (($credential['id'] ?? null) !== $rawId) {
            throw self::malformed('id must equal rawId');
        }

        if (strlen($rawId) > self::MAX_CREDENTIAL_ID_LENGTH) {
            throw self::malformed(
                'the credential id is longer than ' . self::MAX_CREDENTIAL_ID_LENGTH . ' base64url characters',
            );
        }

        if (!is_array($response) || array_is_list($response) && $response !== []) {
            throw self::malformed('response must be an object');
        }

        /** @var array<string, mixed> $response */
        return new self($rawId, $response);
    }

    /**
     * @throws PasskeyVerificationException
     */
    public function clientData(): ClientData
    {
        return ClientData::fromJson($this->binary('clientDataJSON'));
    }

    /**
     * A required base64url field of response, decoded.
     *
     * @throws PasskeyVerificationException
     */
    public function binary(
        string $field,
    ): string {
        $value = $this->optionalBinary($field);

        if ($value === null || $value === '') {
            throw self::malformed("response.$field is required");
        }

        return $value;
    }

    /**
     * An optional base64url field of response, decoded; null when absent.
     *
     * @throws PasskeyVerificationException
     */
    public function optionalBinary(
        string $field,
    ): ?string {
        $value = $this->response[$field] ?? null;

        if ($value === null) {
            return null;
        }

        $decoded = is_string($value) ? Base64Url::decode($value) : null;

        if ($decoded === null) {
            throw self::malformed("response.$field must be a base64url string");
        }

        return $decoded;
    }

    /**
     * The transports the authenticator reported (e.g. "internal", "usb").
     *
     * @return list<string>
     * @throws PasskeyVerificationException
     */
    public function transports(): array
    {
        $transports = $this->response['transports'] ?? [];

        if (
            !is_array($transports)
            || !array_all(
                $transports,
                static fn (mixed $transport): bool => is_string($transport) && preg_match(
                    '/^[a-z-]{1,32}$/',
                    $transport,
                ) === 1,
            )
        ) {
            throw self::malformed('response.transports must be a list of transport names such as "internal" or "usb"');
        }

        /** @var array<string> $transports */
        return array_values(array_unique($transports));
    }

    private static function malformed(
        string $detail,
    ): PasskeyVerificationException {
        return PasskeyVerificationException::because(PasskeyFailureReason::MalformedResponse, $detail);
    }
}
