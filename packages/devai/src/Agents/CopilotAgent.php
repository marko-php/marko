<?php

declare(strict_types=1);

namespace Marko\DevAi\Agents;

use Marko\DevAi\Contract\AgentInterface;
use Marko\DevAi\Installation\InstallationContext;
use Marko\DevAi\ValueObject\GuidelinesContent;
use Marko\DevAi\ValueObject\McpRegistration;
use Marko\DevAi\Writing\GuidelinesWriter;

readonly class CopilotAgent implements AgentInterface
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

    public function install(
        InstallationContext $ctx,
        string $projectRoot,
    ): void {
        $this->writeGuidelines($ctx->guidelines, $projectRoot);
        $this->registerMcpServer($ctx->mcpRegistration, $projectRoot);
    }

    private function writeGuidelines(
        GuidelinesContent $content,
        string $projectRoot,
    ): void {
        $githubDir = $projectRoot . '/.github';

        if (!is_dir($githubDir)) {
            mkdir($githubDir, 0755, true);
        }

        GuidelinesWriter::write($githubDir . '/copilot-instructions.md', $content->body);
        GuidelinesWriter::write($projectRoot . '/AGENTS.md', $content->body);
    }

    private function registerMcpServer(
        McpRegistration $registration,
        string $projectRoot,
    ): void {
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
