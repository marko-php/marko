<?php

declare(strict_types=1);

namespace Marko\DevAi\Agents;

use Marko\DevAi\Contract\SupportsGuidelines;
use Marko\DevAi\Contract\SupportsMcp;
use Marko\DevAi\Process\CommandRunnerInterface;
use Marko\DevAi\ValueObject\GuidelinesContent;
use Marko\DevAi\ValueObject\McpRegistration;

class CursorAgent extends AbstractAgent implements SupportsGuidelines, SupportsMcp
{
    public function __construct(
        private CommandRunnerInterface $runner,
    ) {}

    public function name(): string
    {
        return 'cursor';
    }

    public function displayName(): string
    {
        return 'Cursor';
    }

    public function isInstalled(): bool
    {
        return $this->runner->isOnPath('cursor');
    }

    public function writeGuidelines(GuidelinesContent $content, string $projectRoot): void
    {
        $rulesDir = $projectRoot . '/.cursor/rules';

        if (!is_dir($rulesDir)) {
            mkdir($rulesDir, 0755, true);
        }

        $mdc = "---\ndescription: Marko Framework guidelines\nalwaysApply: true\n---\n\n" . $content->body;
        file_put_contents($rulesDir . '/marko.mdc', $mdc);

        $agentsPath = $projectRoot . '/AGENTS.md';

        if (!is_file($agentsPath)) {
            file_put_contents($agentsPath, $content->body);
        }
    }

    public function registerMcpServer(McpRegistration $registration, string $projectRoot): void
    {
        $cursorDir = $projectRoot . '/.cursor';

        if (!is_dir($cursorDir)) {
            mkdir($cursorDir, 0755, true);
        }

        $mcpPath = $cursorDir . '/mcp.json';
        $config = is_file($mcpPath)
            ? (json_decode((string) file_get_contents($mcpPath), true) ?: [])
            : [];

        $config['mcpServers'] ??= [];
        $config['mcpServers'][$registration->serverName] = [
            'command' => $registration->command,
            'args' => $registration->args,
            'env' => $registration->env,
        ];

        file_put_contents($mcpPath, json_encode($config, JSON_PRETTY_PRINT));
    }
}
