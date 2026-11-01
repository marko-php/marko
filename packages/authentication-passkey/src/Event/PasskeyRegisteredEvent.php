<?php

declare(strict_types=1);

namespace Marko\AuthenticationPasskey\Event;

use Marko\Authentication\AuthenticatableInterface;
use Marko\AuthenticationPasskey\Entity\PasskeyCredential;
use Marko\Core\Event\Event;

/**
 * Dispatched by PasskeyRegistration after a new passkey is stored.
 */
class PasskeyRegisteredEvent extends Event
{
    public function __construct(
        public readonly AuthenticatableInterface $user,
        public readonly PasskeyCredential $credential,
        public readonly string $guard,
    ) {}
}
