<?php

declare(strict_types=1);

namespace Marko\DevAi\Agents;

use Marko\DevAi\Contract\SupportsGuidelines;
use Marko\DevAi\Contract\SupportsMcp;
use Marko\DevAi\Contract\SupportsSkills;
use Marko\DevAi\Process\CommandRunnerInterface;
use Marko\DevAi\Skills\SkillsDistributor;
use Marko\DevAi\ValueObject\GuidelinesContent;
use Marko\DevAi\ValueObject\McpRegistration;
use Marko\DevAi\ValueObject\SkillBundle;

class CodexAgent extends AbstractAgent implements SupportsGuidelines, SupportsMcp, SupportsSkills
{
    public function __construct(
        private CommandRunnerInterface $runner,
    ) {}

    public function name(): string
    {
        return 'codex';
    }

    public function displayName(): string
    {
        return 'OpenAI Codex';
    }

    public function isInstalled(): bool
    {
        return $this->runner->isOnPath('codex');
    }

    public function writeGuidelines(
        GuidelinesContent $content,
        string $projectRoot,
    ): void
    {
        file_put_contents($projectRoot . '/AGENTS.md', $content->body);
    }

    public function registerMcpServer(
        McpRegistration $reg,
        string $projectRoot,
    ): void
    {
        $args = ['mcp', 'add', $reg->serverName, '--', $reg->command, ...$reg->args];
        $this->runner->run('codex', $args);
    }

    /** @param list<SkillBundle> $bundles */
    public function distributeSkills(
        array $bundles,
        string $projectRoot,
    ): void
    {
        SkillsDistributor::writeBundles($bundles, $projectRoot . '/.agents/skills');
    }
}
