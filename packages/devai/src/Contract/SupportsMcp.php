<?php

declare(strict_types=1);

namespace Marko\DevAi\Contract;

use Marko\DevAi\ValueObject\McpRegistration;

interface SupportsMcp
{
    public function registerMcpServer(McpRegistration $registration, string $projectRoot): void;
}
