<?php

declare(strict_types=1);

namespace Marko\Mcp\Commands;

use JsonException;
use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Mcp\Server\McpServer;

#[Command(name: 'mcp:serve', description: 'Start the Marko MCP server on stdio')]
class ServeCommand implements CommandInterface
{
    public function __construct(
        private McpServer $server,
    ) {}

    /**
     * @throws JsonException
     */
    public function execute(Input $input, Output $output): int
    {
        fwrite(STDERR, "Marko MCP server starting on stdio...\n");
        $this->server->serve();
        fwrite(STDERR, "Marko MCP server shut down.\n");

        return 0;
    }
}
