<?php

declare(strict_types=1);

namespace Marko\AuthenticationPasskey\Event;

use Marko\Core\Event\Event;

/**
 * Dispatched by PasskeyAuthentication when a sign-in or confirmation
 * assertion is rejected. A throttled attempt is not verified, so it
 * dispatches nothing.
 *
 * Carries no secret: the credential id (when the response named one) is a
 * public identifier, so the event is safe to log.
 */
class PasskeyLoginFailedEvent extends Event
{
    public function __construct(
        public readonly string $guard,
        public readonly PasskeyFailureReason $reason,
        public readonly ?string $credentialId = null,
        public readonly ?string $ipAddress = null,
    ) {}
}
