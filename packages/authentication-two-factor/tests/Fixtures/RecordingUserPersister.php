<?php

declare(strict_types=1);

namespace Marko\AuthenticationTwoFactor\Tests\Fixtures;

use Marko\Authentication\AuthenticatableInterface;
use Marko\Authentication\Contracts\UserPersisterInterface;

/**
 * Records every user it is asked to persist.
 */
class RecordingUserPersister implements UserPersisterInterface
{
    /** @var list<AuthenticatableInterface> */
    public array $persisted = [];

    public function persist(
        AuthenticatableInterface $user,
    ): void {
        $this->persisted[] = $user;
    }
}
