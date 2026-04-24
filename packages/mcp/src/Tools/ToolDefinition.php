<?php

declare(strict_types=1);

namespace Marko\Mcp\Tools;

readonly class ToolDefinition
{
    /** @param array<string, mixed> $inputSchema JSON Schema */
    public function __construct(
        public string $name,
        public string $description,
        public array $inputSchema,
        public ToolHandlerInterface $handler,
    ) {}
}
