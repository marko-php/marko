<?php

declare(strict_types=1);

namespace Marko\DevAi\Contract;

use Marko\DevAi\Exceptions\DevAiInstallException;

interface SupportsSettings
{
    /**
     * Write or merge AI-tool settings (e.g. .claude/settings.json).
     *
     * @throws DevAiInstallException when settings already exist and force is false
     */
    public function writeSettings(string $projectRoot, bool $force): void;
}
