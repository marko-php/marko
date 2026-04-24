<?php

declare(strict_types=1);

namespace Marko\DevAi\Agents;

use Marko\DevAi\Contract\AgentInterface;

abstract class AbstractAgent implements AgentInterface
{
    abstract public function name(): string;

    abstract public function displayName(): string;

    public function isInstalled(): bool
    {
        return false;
    }
}
