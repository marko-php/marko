<?php

declare(strict_types=1);

namespace Marko\DevAi\Agents;

use Marko\DevAi\Contract\SupportsGuidelines;
use Marko\DevAi\Contract\SupportsLsp;
use Marko\DevAi\Contract\SupportsMcp;
use Marko\DevAi\Contract\SupportsSkills;
use Marko\DevAi\Process\CommandRunnerInterface;
use Marko\DevAi\Skills\SkillsDistributor;
use Marko\DevAi\ValueObject\GuidelinesContent;
use Marko\DevAi\ValueObject\LspRegistration;
use Marko\DevAi\ValueObject\McpRegistration;
use Marko\DevAi\ValueObject\SkillBundle;

class ClaudeCodeAgent extends AbstractAgent implements SupportsGuidelines, SupportsMcp, SupportsLsp, SupportsSkills
{
    public function __construct(
        private CommandRunnerInterface $runner,
    ) {}

    public function name(): string
    {
        return 'claude-code';
    }

    public function displayName(): string
    {
        return 'Claude Code';
    }

    public function isInstalled(): bool
    {
        return $this->runner->isOnPath('claude');
    }

    public function writeGuidelines(
        GuidelinesContent $content,
        string $projectRoot,
    ): void
    {
        file_put_contents($projectRoot . '/AGENTS.md', $content->body);
        $claudeMd = "# Project Instructions\n\n@AGENTS.md\n\n## Marko skills\n\n"
            . "Marko ships task-oriented skills under `.claude/skills/` (e.g. `marko-create-module`, `marko-create-plugin`).\n"
            . "Claude Code auto-loads a skill when its description matches the user's request — you don't need to invoke them manually.\n"
            . "If you want to inspect what's available, list `.claude/skills/`.\n";
        file_put_contents($projectRoot . '/CLAUDE.md', $claudeMd);
    }

    public function registerMcpServer(
        McpRegistration $reg,
        string $projectRoot,
    ): void
    {
        $listResult = $this->runner->run('claude', ['mcp', 'list']);
        if ($this->mcpListContainsServer($listResult['stdout'], $reg->serverName)) {
            return;
        }
        $this->runner->run(
            'claude',
            ['mcp', 'add', '-s', 'local', '-t', $reg->transport, $reg->serverName, $reg->command, ...$reg->args]
        );
    }

    /**
     * Match the server name as a leading line token in `claude mcp list` output,
     * not a naive substring — otherwise registering "marko-mcp" would silently
     * skip when an unrelated "marko-mcp-staging" already exists.
     */
    private function mcpListContainsServer(
        string $listStdout,
        string $serverName,
    ): bool
    {
        $pattern = '/^' . preg_quote($serverName, '/') . '(?:\s|:|$)/m';

        return preg_match($pattern, $listStdout) === 1;
    }

    public function registerLspServer(
        LspRegistration $reg,
        string $projectRoot,
    ): void
    {
        $lspDir = $projectRoot . '/.claude/plugins/marko';
        if (!is_dir($lspDir)) {
            mkdir($lspDir, 0755, true);
        }
        $config = [
            'name' => $reg->serverName,
            'command' => $reg->command,
            'args' => $reg->args,
            'fileExtensions' => $reg->fileExtensions,
        ];
        file_put_contents($lspDir . '/.lsp.json', json_encode($config, JSON_PRETTY_PRINT));
    }

    /**
     * @param list<SkillBundle> $bundles
     * @param list<string> $previouslyShipped
     */
    public function distributeSkills(
        array $bundles,
        string $projectRoot,
        array $previouslyShipped = [],
    ): void {
        SkillsDistributor::syncBundles($bundles, $projectRoot . '/.claude/skills', $previouslyShipped);
    }
}
