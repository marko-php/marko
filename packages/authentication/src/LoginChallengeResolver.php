<?php

declare(strict_types=1);

namespace Marko\Authentication;

use Marko\Authentication\Contracts\LoginChallengeInterface;
use Marko\Authentication\Exceptions\AuthException;
use Marko\Core\Container\ContainerInterface;

/**
 * Resolves the login challenges a guard lists in authentication.guards.<name>.challenges.
 *
 * Each entry is a LoginChallengeInterface class name, resolved from the
 * container, so a challenge can take its own dependencies. Order is kept: a
 * guard asks the challenges in the order they are listed.
 */
readonly class LoginChallengeResolver
{
    public function __construct(
        private ContainerInterface $container,
    ) {}

    /**
     * @param array<string, mixed> $guardConfig
     * @return list<LoginChallengeInterface>
     * @throws AuthException
     */
    public function forGuard(
        string $guard,
        array $guardConfig,
    ): array {
        $classes = $guardConfig['challenges'] ?? [];

        if (!is_array($classes) || !array_is_list($classes)) {
            throw AuthException::invalidChallenges($guard);
        }

        $challenges = [];

        foreach ($classes as $class) {
            if (!is_string($class)) {
                throw AuthException::invalidChallenges($guard);
            }

            if (!is_a($class, LoginChallengeInterface::class, true)) {
                throw AuthException::invalidChallengeClass($guard, $class);
            }

            /** @var LoginChallengeInterface $challenge */
            $challenge = $this->container->get($class);
            $challenges[] = $challenge;
        }

        return $challenges;
    }
}
