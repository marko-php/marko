<?php

declare(strict_types=1);

namespace Marko\AuthenticationPasskey\Event;

use Marko\Authentication\AuthenticatableInterface;
use Marko\AuthenticationPasskey\Entity\PasskeyCredential;
use Marko\Core\Event\Event;

/**
 * Dispatched by PasskeyManager after a user deletes one of their passkeys.
 * The credential is no longer stored.
 */
class PasskeyDeletedEvent extends Event
{
    public function __construct(
        public readonly AuthenticatableInterface $user,
        public readonly PasskeyCredential $credential,
        public readonly string $guard,
    ) {}
}
