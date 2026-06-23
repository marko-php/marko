<?php

declare(strict_types=1);

namespace Marko\DevAi\Agents;

use Marko\DevAi\Contract\AgentInterface;
use Marko\DevAi\Installation\InstallationContext;
use Marko\DevAi\Process\CommandRunnerInterface;
use Marko\DevAi\Skills\SkillsDistributor;
use Marko\DevAi\ValueObject\GuidelinesContent;
use Marko\DevAi\ValueObject\McpRegistration;
use Marko\DevAi\ValueObject\SkillBundle;
use Marko\DevAi\Writing\GuidelinesWriter;

readonly class CodexAgent implements AgentInterface
{
    public function __construct(
        private CommandRunnerInterface $commandRunner,
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
        return $this->commandRunner->isOnPath('codex');
    }

    public function install(
        InstallationContext $ctx,
        string $projectRoot,
    ): void {
        $this->writeGuidelines($ctx->guidelines, $projectRoot);
        $this->registerMcpServer($ctx->mcpRegistration);
        $this->distributeSkills($ctx->skills, $projectRoot, $ctx->previouslyShipped);
    }

    private function writeGuidelines(
        GuidelinesContent $content,
        string $projectRoot,
    ): void {
        GuidelinesWriter::write($projectRoot . '/AGENTS.md', $content->body);
    }

    private function registerMcpServer(McpRegistration $registration): void
    {
        $args = ['mcp', 'add', $registration->serverName, '--', $registration->command, ...$registration->args];
        $this->commandRunner->run('codex', $args);
    }

    /**
     * @param list<SkillBundle> $bundles
     * @param list<string> $previouslyShipped
     */
    private function distributeSkills(
        array $bundles,
        string $projectRoot,
        array $previouslyShipped,
    ): void {
        SkillsDistributor::syncBundles($bundles, $projectRoot . '/.agents/skills', $previouslyShipped);
    }
}
