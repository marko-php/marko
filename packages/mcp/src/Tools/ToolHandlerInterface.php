<?php

declare(strict_types=1);

namespace Marko\Mcp\Tools;

interface ToolHandlerInterface
{
    /**
     * @param array<string, mixed> $arguments
     * @return array{content: list<array{type: string, text: string}>, isError?: bool}
     */
    public function handle(array $arguments): array;
}
