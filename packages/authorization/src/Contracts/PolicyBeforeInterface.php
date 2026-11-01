<?php

declare(strict_types=1);

namespace Marko\Authorization\Contracts;

use Marko\Authorization\AuthorizableInterface;

/**
 * A policy-level before check, implemented by the policy itself.
 *
 * It runs only for the abilities the policy defines, after every global
 * #[GateHook] returned null and before the ability method. Policies are
 * built without dependency injection, so a before check can only use the
 * user and the arguments.
 */
interface PolicyBeforeInterface
{
    /**
     * @param AuthorizableInterface|null $user The current user, or null for a guest
     * @param array<mixed> $arguments The arguments passed to the ability check
     * @return bool|null True to allow, false to deny, null to call the ability method
     */
    public function before(
        ?AuthorizableInterface $user,
        string $ability,
        array $arguments,
    ): ?bool;
}
