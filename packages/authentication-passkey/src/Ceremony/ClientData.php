<?php

declare(strict_types=1);

namespace Marko\AuthenticationPasskey\Ceremony;

use Marko\AuthenticationPasskey\Event\PasskeyFailureReason;
use Marko\AuthenticationPasskey\Exceptions\PasskeyVerificationException;

/**
 * The browser's CollectedClientData: which ceremony it signed, for which
 * challenge, on which origin. The raw JSON is kept because signatures cover
 * its exact bytes.
 */
readonly class ClientData
{
    public function __construct(
        public string $json,
        public string $type,
        public string $challenge,
        public string $origin,
        public bool $crossOrigin,
    ) {}

    /**
     * @throws PasskeyVerificationException
     */
    public static function fromJson(
        string $json,
    ): self {
        $data = json_decode($json, true);

        if (
            !is_array($data)
            || !is_string($data['type'] ?? null)
            || !is_string($data['challenge'] ?? null)
            || !is_string($data['origin'] ?? null)
        ) {
            throw PasskeyVerificationException::because(
                PasskeyFailureReason::MalformedResponse,
                'clientDataJSON is not a JSON object with string type, challenge and origin',
            );
        }

        $challenge = Base64Url::decode($data['challenge']);

        if ($challenge === null) {
            throw PasskeyVerificationException::because(
                PasskeyFailureReason::MalformedResponse,
                'clientDataJSON.challenge is not base64url',
            );
        }

        return new self(
            json: $json,
            type: $data['type'],
            challenge: $challenge,
            origin: $data['origin'],
            crossOrigin: ($data['crossOrigin'] ?? false) === true,
        );
    }
}
