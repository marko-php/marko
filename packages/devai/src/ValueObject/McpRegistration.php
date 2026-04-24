<?php

declare(strict_types=1);

namespace Marko\DevAi\ValueObject;

readonly class McpRegistration
{
    /**
     * @param list<string> $args
     * @param array<string, string> $env
     */
    public function __construct(
        public string $serverName,
        public string $command,
        public array $args,
        public array $env = [],
        public string $transport = 'stdio',
    ) {}
}
