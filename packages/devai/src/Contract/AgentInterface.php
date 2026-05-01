<?php

declare(strict_types=1);

namespace Marko\DevAi\Contract;

interface AgentInterface
{
    public function name(): string;

    public function displayName(): string;

    public function isInstalled(): bool;
}
