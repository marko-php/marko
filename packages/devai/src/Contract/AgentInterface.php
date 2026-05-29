<?php

declare(strict_types=1);

namespace Marko\DevAi\Contract;

use Marko\DevAi\Exceptions\DevAiInstallException;
use Marko\DevAi\Installation\InstallationContext;

interface AgentInterface
{
    public function name(): string;

    public function displayName(): string;

    public function isInstalled(): bool;

    /**
     * Install Marko AI tooling for this agent into the project.
     *
     * Each agent decides which artifacts it writes (guidelines, settings, MCP
     * registration, skills, LSP deps). Everything the install needs is carried
     * on the context — rendered once at the orchestrator level and shared.
     *
     * @throws DevAiInstallException when the install cannot proceed (e.g. settings
     *                               already registered and $ctx->force is false)
     */
    public function install(
        InstallationContext $ctx,
        string $projectRoot,
    ): void;
}
