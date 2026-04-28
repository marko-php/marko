<?php

declare(strict_types=1);

namespace Marko\Mcp\Tools\Runtime;

use Marko\Mcp\Tools\Runtime\Contracts\ConsoleDispatcherInterface;
use Marko\Mcp\Tools\ToolDefinition;
use Marko\Mcp\Tools\ToolHandlerInterface;
use Throwable;

readonly class RunConsoleCommandTool implements ToolHandlerInterface
{
    public function __construct(private ConsoleDispatcherInterface $dispatcher) {}

    public static function definition(ConsoleDispatcherInterface $dispatcher): ToolDefinition
    {
        return new ToolDefinition(
            name: 'run_console_command',
            description: 'Run a Marko console command and return its output',
            inputSchema: [
                'type' => 'object',
                'required' => ['command'],
                'properties' => [
                    'command' => ['type' => 'string', 'description' => 'The command name to run'],
                    'args' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Optional arguments'],
                ],
            ],
            handler: new self($dispatcher),
        );
    }

    public function handle(array $arguments): array
    {
        $command = (string) ($arguments['command'] ?? '');
        $args = (array) ($arguments['args'] ?? []);

        try {
            $result = $this->dispatcher->dispatch($command, $args);
        } catch (Throwable $e) {
            return [
                'content' => [['type' => 'text', 'text' => 'ERROR: ' . $e->getMessage()]],
                'isError' => true,
            ];
        }

        $text = "exitCode: {$result['exitCode']}\n";

        if ($result['stdout'] !== '') {
            $text .= "stdout:\n{$result['stdout']}\n";
        }

        if ($result['stderr'] !== '') {
            $text .= "stderr:\n{$result['stderr']}\n";
        }

        return ['content' => [['type' => 'text', 'text' => rtrim($text)]]];
    }
}
