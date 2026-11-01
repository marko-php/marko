<?php

declare(strict_types=1);

namespace Marko\AuthenticationTwoFactor;

use Marko\Authentication\AuthenticatableInterface;
use Marko\Authentication\Contracts\LoginChallengeInterface;
use Marko\AuthenticationTwoFactor\Contracts\TwoFactorAuthenticatableInterface;
use Marko\AuthenticationTwoFactor\Contracts\TwoFactorManagerInterface;

/**
 * Holds a password login until the user enters a two-factor code.
 *
 * List it in a session guard's authentication.guards.<name>.challenges. It
 * applies to users whose entity implements TwoFactorAuthenticatableInterface
 * and who have confirmed two-factor; everyone else logs in as before.
 */
readonly class TwoFactorLoginChallenge implements LoginChallengeInterface
{
    public const string NAME = 'two_factor';

    public function __construct(
        private TwoFactorManagerInterface $twoFactorManager,
    ) {}

    public function requiresChallenge(
        AuthenticatableInterface $user,
    ): bool {
        return $user instanceof TwoFactorAuthenticatableInterface && $this->twoFactorManager->isEnabled($user);
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
