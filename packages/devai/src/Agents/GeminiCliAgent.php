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

class GeminiCliAgent implements AgentInterface
{
    public function __construct(
        private CommandRunnerInterface $runner,
    ) {}

    public function name(): string
    {
        return 'gemini-cli';
    }

    public function displayName(): string
    {
        return 'Gemini CLI';
    }

    public function isInstalled(): bool
    {
        return $this->runner->isOnPath('gemini');
    }

    public function install(
        InstallationContext $ctx,
        string $projectRoot,
    ): void
    {
        $this->writeGuidelines($ctx->guidelines, $projectRoot);
        $this->registerMcpServer($ctx->mcpRegistration);
        $this->distributeSkills($ctx->skills, $projectRoot, $ctx->previouslyShipped);
    }

    private function writeGuidelines(
        GuidelinesContent $content,
        string $projectRoot,
    ): void {
        file_put_contents($projectRoot . '/GEMINI.md', $content->body);
        $agentsPath = $projectRoot . '/AGENTS.md';
        if (!is_file($agentsPath)) {
            file_put_contents($agentsPath, $content->body);
        }
    }

    private function registerMcpServer(McpRegistration $registration): void
    {
        $args = ['mcp', 'add', '-s', 'project', '-t', $registration->transport, $registration->serverName, $registration->command, ...$registration->args];
        $this->runner->run('gemini', $args);
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
        SkillsDistributor::syncBundles($bundles, $projectRoot . '/.gemini/skills', $previouslyShipped);
    }
}
