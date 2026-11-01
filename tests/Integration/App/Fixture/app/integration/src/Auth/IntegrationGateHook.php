<?php

declare(strict_types=1);

namespace Marko\Integration\Fixture\Auth;

use Marko\Authorization\Attributes\GateHook;
use Marko\Authorization\AuthorizableInterface;
use Marko\Authorization\Contracts\GateHookInterface;

/**
 * Allows only the 'integration-hook' ability, which nothing else defines, so
 * an allowed check proves the hook was discovered and wired into the Gate.
 */
#[GateHook(priority: 10)]
class IntegrationGateHook implements GateHookInterface
{
    public function before(
        ?AuthorizableInterface $user,
        string $ability,
        array $arguments,
    ): ?bool {
        return $ability === 'integration-hook' ? true : null;
    }
}
