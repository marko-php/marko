<?php

declare(strict_types=1);

namespace Marko\DevAi\Agents;

use Marko\DevAi\Contract\SupportsGuidelines;
use Marko\DevAi\Contract\SupportsSkills;
use Marko\DevAi\ValueObject\GuidelinesContent;
use Marko\DevAi\ValueObject\SkillBundle;

class JunieAgent extends AbstractAgent implements SupportsGuidelines, SupportsSkills
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

    public function writeGuidelines(GuidelinesContent $content, string $projectRoot): void
    {
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

    /** @param list<SkillBundle> $bundles */
    public function distributeSkills(array $bundles, string $projectRoot): void
    {
        $skillsDir = $projectRoot . '/junie/skills';

        if (!is_dir($skillsDir)) {
            mkdir($skillsDir, 0755, true);
        }

        foreach ($bundles as $bundle) {
            foreach ($bundle->skills as $filename => $content) {
                file_put_contents($skillsDir . '/' . $filename, $content);
            }
        }
    }
}
