<?php

declare(strict_types=1);

namespace Marko\Authorization\Event;

use Marko\Authorization\AuthorizableInterface;
use Marko\Core\Event\Event;

/**
 * Dispatched after the Gate decides an ability, for auditing and debugging.
 *
 * Observers see the decision but cannot change it.
 */
class AbilityCheckedEvent extends Event
{
    /**
     * @param array<mixed> $arguments The arguments passed to the ability check
     * @param class-string|null $decidedBy The hook or policy class that decided; null for the token scope, a closure or the default deny
     */
    public function __construct(
        public readonly ?AuthorizableInterface $user,
        public readonly string $ability,
        public readonly array $arguments,
        public readonly bool $result,
        public readonly AbilityDecisionSource $source,
        public readonly ?string $decidedBy = null,
    ) {}
}
