<?php

declare(strict_types=1);

namespace Marko\AuthenticationPasskey\Ceremony;

use Marko\AuthenticationPasskey\Config\PasskeyConfig;
use Marko\AuthenticationPasskey\Event\PasskeyFailureReason;
use Marko\AuthenticationPasskey\Exceptions\PasskeyConfigurationException;
use Marko\AuthenticationPasskey\Exceptions\PasskeyVerificationException;

/**
 * Checks the client data of a response before any engine sees it: the
 * ceremony type, the challenge (in constant time) and the origin, which must
 * be exactly one of allowed_origins. Cross-origin iframes are refused.
 */
readonly class ClientDataVerifier
{
    public const string CREATE = 'webauthn.create';

    public const string GET = 'webauthn.get';

    public function __construct(
        private PasskeyConfig $config,
    ) {}

    /**
     * @throws PasskeyVerificationException|PasskeyConfigurationException
     */
    public function verify(
        ClientData $clientData,
        string $expectedType,
        string $expectedChallenge,
    ): void {
        if ($clientData->type !== $expectedType) {
            throw PasskeyVerificationException::because(
                PasskeyFailureReason::MalformedResponse,
                "clientDataJSON.type is '$clientData->type', expected '$expectedType'",
            );
        }

        if (!hash_equals($expectedChallenge, $clientData->challenge)) {
            throw PasskeyVerificationException::because(
                PasskeyFailureReason::ChallengeMismatch,
                'the response signs a different challenge than the one issued for this session',
            );
        }

        if ($clientData->crossOrigin) {
            throw PasskeyVerificationException::because(
                PasskeyFailureReason::OriginMismatch,
                'the response was created in a cross-origin iframe',
            );
        }

        if (!in_array(strtolower($clientData->origin), $this->config->allowedOrigins(), true)) {
            throw PasskeyVerificationException::because(
                PasskeyFailureReason::OriginMismatch,
                "origin '$clientData->origin' is not in authentication-passkey.allowed_origins",
            );
        }
    }
}
