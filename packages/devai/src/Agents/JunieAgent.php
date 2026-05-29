<?php

declare(strict_types=1);

namespace Marko\DevAi\Agents;

use Marko\DevAi\Contract\AgentInterface;
use Marko\DevAi\Installation\InstallationContext;
use Marko\DevAi\Skills\SkillsDistributor;
use Marko\DevAi\ValueObject\GuidelinesContent;
use Marko\DevAi\ValueObject\SkillBundle;

class JunieAgent implements AgentInterface
{
    public function __construct(
        private string $projectRoot,
    ) {}

    public function name(): string
    {
        return 'junie';
    }

    public function displayName(): string
    {
        return 'JetBrains Junie';
    }

    public function isInstalled(): bool
    {
        return is_dir($this->projectRoot . '/.idea') || is_dir($this->projectRoot . '/junie');
    }

    public function install(
        InstallationContext $ctx,
        string $projectRoot,
    ): void
    {
        $this->writeGuidelines($ctx->guidelines, $projectRoot);
        $this->distributeSkills($ctx->skills, $projectRoot, $ctx->previouslyShipped);
    }

    private function writeGuidelines(
        GuidelinesContent $content,
        string $projectRoot,
    ): void {
        $junieDir = $projectRoot . '/junie';

        if (!is_dir($junieDir)) {
            mkdir($junieDir, 0755, true);
        }

        file_put_contents($junieDir . '/guidelines.md', $content->body);

        $agentsPath = $projectRoot . '/AGENTS.md';

        if (!is_file($agentsPath)) {
            file_put_contents($agentsPath, $content->body);
        }
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
        SkillsDistributor::syncBundles($bundles, $projectRoot . '/junie/skills', $previouslyShipped);
    }
}
