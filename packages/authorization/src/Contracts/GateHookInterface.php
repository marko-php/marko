<?php

declare(strict_types=1);

namespace Marko\Authorization\Contracts;

use Marko\Authorization\AuthorizableInterface;

/**
 * Decides an ability before any gate closure or policy runs.
 *
 * Mark the implementing class with #[GateHook] so it is discovered at boot.
 * Hooks run in priority order (highest first) after the scoped-token check
 * and before define() closures and policies; the first non-null result wins.
 * A hook can never widen what a scoped API token allows.
 */
interface GateHookInterface
{
    /**
     * @param AuthorizableInterface|null $user The current user, or null for a guest
     * @param array<mixed> $arguments The arguments passed to the ability check
     * @return bool|null True to allow, false to deny, null to fall through to the next hook, closure or policy
     */
    public function before(
        ?AuthorizableInterface $user,
        string $ability,
        array $arguments,
    ): ?bool;
}
