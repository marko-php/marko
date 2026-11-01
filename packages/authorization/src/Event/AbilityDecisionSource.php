<?php

declare(strict_types=1);

namespace Marko\Authorization\Event;

/**
 * What decided an ability check.
 */
enum AbilityDecisionSource: string
{
    /** The scoped credential (an API token) does not grant the ability */
    case TokenScope = 'token_scope';

    /** A #[GateHook] returned true or false */
    case GateHook = 'gate_hook';

    /** A policy's PolicyBeforeInterface::before() returned true or false */
    case PolicyBefore = 'policy_before';

    /** A closure registered with define() */
    case Closure = 'closure';

    /** A policy's ability method */
    case Policy = 'policy';

    /** Nothing defined the ability, so it was denied */
    case DefaultDeny = 'default_deny';
}
