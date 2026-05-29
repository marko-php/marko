<?php

declare(strict_types=1);

namespace Marko\DevAi\Installation;

use Marko\DevAi\ValueObject\GuidelinesContent;
use Marko\DevAi\ValueObject\McpRegistration;
use Marko\DevAi\ValueObject\SkillBundle;

/**
 * Everything an agent's install() needs in a single value object.
 *
 * The command builds the context from user options (selected agents, force,
 * gitignore, skip-lsp). The orchestrator then renders the shared install data
 * once (guidelines, skills, previously-shipped, MCP registration) and produces
 * an enriched copy via withInstallData() before invoking each agent — so every
 * agent installs from identical, already-computed inputs.
 */
readonly class InstallationContext
{
    /**
     * @param list<string> $selectedAgents
     * @param list<SkillBundle> $skills
     * @param list<string> $previouslyShipped
     */
    public function __construct(
        public array $selectedAgents,
        public bool $force = false,
        public bool $updateGitignore = false,
        public bool $skipLspDeps = false,
        public ?GuidelinesContent $guidelines = null,
        public array $skills = [],
        public array $previouslyShipped = [],
        public ?McpRegistration $mcpRegistration = null,
    ) {}

    /**
     * Return an enriched copy carrying the shared install data the orchestrator
     * computes once. User options are preserved unchanged.
     *
     * @param list<SkillBundle> $skills
     * @param list<string> $previouslyShipped
     */
    public function withInstallData(
        GuidelinesContent $guidelines,
        array $skills,
        array $previouslyShipped,
        McpRegistration $mcpRegistration,
    ): self {
        return new self(
            selectedAgents: $this->selectedAgents,
            force: $this->force,
            updateGitignore: $this->updateGitignore,
            skipLspDeps: $this->skipLspDeps,
            guidelines: $guidelines,
            skills: $skills,
            previouslyShipped: $previouslyShipped,
            mcpRegistration: $mcpRegistration,
        );
    }
}
