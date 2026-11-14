<?php

declare(strict_types=1);

namespace Marko\Mcp\Tools\Runtime;

use Marko\Mcp\Tools\Runtime\Adapters\MarkoConsoleDispatcher;
use Marko\Mcp\Tools\ToolDefinition;
use Marko\Mcp\Tools\ToolHandlerInterface;
use Throwable;

/**
 * The run_console_command MCP tool.
 *
 * The agent can be steered by content it reads, so the server operator decides what it may run. A command runs only
 * when its name or alias is in the mcp.console.allowed_commands config list. A command marked
 * #[Command(destructive: true)] (db:reset, cache:clear, queue:clear...) also needs mcp.console.allow_destructive
 * (MCP_ALLOW_DESTRUCTIVE). The marker is read from the command's definition, never inferred from its options: the
 * agent can pass --force itself, so a --force flag alone guards nothing.
 */
readonly class RunConsoleCommandTool implements ToolHandlerInterface
{
    /**
     * @param list<string> $allowedCommands
     */
    public function __construct(
        private MarkoConsoleDispatcher $dispatcher,
        private array $allowedCommands = [],
        private bool $allowDestructive = false,
    ) {}

    /**
     * @param list<string> $allowedCommands
     */
    public static function definition(
        MarkoConsoleDispatcher $dispatcher,
        array $allowedCommands = [],
        bool $allowDestructive = false,
    ): ToolDefinition {
        $allowed = $allowedCommands !== [] ? implode(', ', $allowedCommands) : '(none)';

        return new ToolDefinition(
            name: 'run_console_command',
            description: "Run a Marko console command and return its output. Allowed commands: $allowed",
            inputSchema: [
                'type' => 'object',
                'required' => ['command'],
                'properties' => [
                    'command' => ['type' => 'string', 'description' => 'The command name to run'],
                    'args' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Optional arguments'],
                ],
            ],
            handler: new self($dispatcher, $allowedCommands, $allowDestructive),
        );
    }

    public function handle(
        array $arguments,
    ): array {
        $command = (string) ($arguments['command'] ?? '');
        $args = (array) ($arguments['args'] ?? []);

        try {
            $refusal = $this->refusalFor($command);

            if ($refusal !== null) {
                return $this->error($refusal);
            }

            $result = $this->dispatcher->dispatch($command, $args);
        } catch (Throwable $e) {
            return $this->error($e->getMessage());
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

    /**
     * Why the command may not run, or null when it may.
     */
    private function refusalFor(
        string $command,
    ): ?string {
        $definition = $this->dispatcher->find($command);
        $names = array_unique([$command, $definition?->name ?? $command]);

        if (array_intersect($names, $this->allowedCommands) === []) {
            $allowed = $this->allowedCommands !== [] ? implode(', ', $this->allowedCommands) : '(none)';

            return "Command '$command' is not allowed. run_console_command only runs the commands in the "
                . "mcp.console.allowed_commands config key. Allowed: $allowed";
        }

        if (
            $definition !== null
            && $definition->destructive
            && ! $this->allowDestructive
        ) {
            return "Command '$command' is destructive (#[Command(destructive: true)]) and is disabled. The server "
                . 'operator must set the mcp.console.allow_destructive config key to true (MCP_ALLOW_DESTRUCTIVE=true) '
                . 'to run it; passing --force cannot enable it.';
        }

        return null;
    }

    /**
     * @return array{content: list<array{type: string, text: string}>, isError: true}
     */
    private function error(
        string $message,
    ): array {
        return [
            'content' => [['type' => 'text', 'text' => 'ERROR: ' . $message]],
            'isError' => true,
        ];
    }
}
