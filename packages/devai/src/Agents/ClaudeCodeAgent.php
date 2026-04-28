<?php

declare(strict_types=1);

namespace Marko\DevAi\Agents;

use Marko\DevAi\Contract\SupportsGuidelines;
use Marko\DevAi\Contract\SupportsLsp;
use Marko\DevAi\Contract\SupportsMcp;
use Marko\DevAi\Contract\SupportsSkills;
use Marko\DevAi\Process\CommandRunnerInterface;
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
        $claudeMd = "# Project Instructions\n\n@AGENTS.md\n\n## Claude-Specific\n\nSee `.claude/skills/` for available Marko skills.\n";
        file_put_contents($projectRoot . '/CLAUDE.md', $claudeMd);
    }

    public function registerMcpServer(
        McpRegistration $reg,
        string $projectRoot,
    ): void
    {
        $listResult = $this->runner->run('claude', ['mcp', 'list']);
        if (str_contains($listResult['stdout'], $reg->serverName)) {
            return;
        }
        $this->runner->run(
            'claude',
            ['mcp', 'add', '-s', 'local', '-t', $reg->transport, $reg->serverName, $reg->command, ...$reg->args]
        );
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

    /** @param list<SkillBundle> $bundles */
    public function distributeSkills(
        array $bundles,
        string $projectRoot,
    ): void
    {
        $skillsDir = $projectRoot . '/.claude/skills';
        if (!is_dir($skillsDir)) {
            mkdir($skillsDir, 0755, true);
        }
        foreach ($bundles as $bundle) {
            foreach ($bundle->skills as $filename => $content) {
                $target = $skillsDir . '/' . $filename;
                $dir = dirname($target);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                file_put_contents($target, $content);
            }
        }
    }
}
