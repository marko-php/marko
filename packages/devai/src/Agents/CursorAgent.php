<?php

declare(strict_types=1);

namespace Marko\DevAi\Agents;

use Marko\DevAi\Contract\AgentInterface;
use Marko\DevAi\Installation\InstallationContext;
use Marko\DevAi\Process\CommandRunnerInterface;
use Marko\DevAi\ValueObject\GuidelinesContent;
use Marko\DevAi\ValueObject\McpRegistration;
use Marko\DevAi\Writing\GuidelinesWriter;

readonly class CursorAgent implements AgentInterface
{
    public function __construct(
        private CommandRunnerInterface $commandRunner,
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
        return $this->commandRunner->isOnPath('cursor');
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
        $rulesDir = $projectRoot . '/.cursor/rules';

        if (!is_dir($rulesDir)) {
            mkdir($rulesDir, 0755, true);
        }

        $mdcPath = $rulesDir . '/marko.mdc';
        $frontmatter = "---\ndescription: Marko Framework guidelines\nalwaysApply: true\n---\n\n";

        if (!is_file($mdcPath)) {
            file_put_contents(
                $mdcPath,
                $frontmatter
                . GuidelinesWriter::MARKER_BEGIN . "\n"
                . $content->body . "\n"
                . GuidelinesWriter::MARKER_END . "\n",
            );
        } else {
            GuidelinesWriter::write($mdcPath, $content->body);
        }

        $agentsPath = $projectRoot . '/AGENTS.md';

        GuidelinesWriter::write($agentsPath, $content->body);
    }

    private function registerMcpServer(
        McpRegistration $registration,
        string $projectRoot,
    ): void {
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
