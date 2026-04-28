<?php

declare(strict_types=1);

namespace Marko\DevAi\Installation;

readonly class InstallationContext
{
    /** @param list<string> $selectedAgents */
    public function __construct(
        public array $selectedAgents,
        public string $docsDriver,
        public bool $updateGitignore = false,
    ) {}
}
