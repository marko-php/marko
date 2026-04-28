<?php

declare(strict_types=1);

namespace Marko\DevAi\Agents;

use Marko\DevAi\Contract\SupportsGuidelines;
use Marko\DevAi\Contract\SupportsMcp;
use Marko\DevAi\ValueObject\GuidelinesContent;
use Marko\DevAi\ValueObject\McpRegistration;

class CopilotAgent extends AbstractAgent implements SupportsGuidelines, SupportsMcp
{
    public function __construct(
        private string $projectRoot,
    ) {}

    public function name(): string
    {
        return 'copilot';
    }

    public function displayName(): string
    {
        return 'GitHub Copilot';
    }

    public function isInstalled(): bool
    {
        return is_dir($this->projectRoot . '/.github');
    }

    public function writeGuidelines(GuidelinesContent $content, string $projectRoot): void
    {
        $githubDir = $projectRoot . '/.github';

        if (!is_dir($githubDir)) {
            mkdir($githubDir, 0755, true);
        }

        file_put_contents($githubDir . '/copilot-instructions.md', $content->body);

        $agentsPath = $projectRoot . '/AGENTS.md';

        if (!is_file($agentsPath)) {
            file_put_contents($agentsPath, $content->body);
        }
    }

    public function registerMcpServer(McpRegistration $registration, string $projectRoot): void
    {
        $vscodeDir = $projectRoot . '/.vscode';

        if (!is_dir($vscodeDir)) {
            mkdir($vscodeDir, 0755, true);
        }

        $mcpPath = $vscodeDir . '/mcp.json';
        $config = is_file($mcpPath)
            ? (json_decode((string) file_get_contents($mcpPath), true) ?: [])
            : [];

        $config['servers'] ??= [];
        $config['servers'][$registration->serverName] = [
            'type' => $registration->transport,
            'command' => $registration->command,
            'args' => $registration->args,
            'env' => $registration->env,
        ];

        file_put_contents($mcpPath, json_encode($config, JSON_PRETTY_PRINT));
    }
}
