<?php

declare(strict_types=1);

namespace Marko\AuthenticationPasskey\Tests\Fixtures;

use Marko\Authentication\AuthenticatableInterface;
use Marko\Authentication\Contracts\LoginChallengeInterface;

/**
 * A login challenge every user must complete, standing in for a two-factor code.
 */
class AlwaysRequiredChallenge implements LoginChallengeInterface
{
    public function requiresChallenge(
        AuthenticatableInterface $user,
    ): bool {
        return true;
    }

    public function getName(): string
    {
        return 'two_factor';
    }
}
