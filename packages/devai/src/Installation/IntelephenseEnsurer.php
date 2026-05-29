<?php

declare(strict_types=1);

namespace Marko\DevAi\Installation;

use Marko\DevAi\Exceptions\DevAiInstallException;
use Marko\DevAi\Process\CommandRunnerInterface;

class IntelephenseEnsurer implements IntelephenseEnsurerInterface
{
    public function __construct(private CommandRunnerInterface $runner) {}

    /**
     * Ensure intelephense is available globally via npm.
     *
     * Returns an EnsureResult describing what happened:
     *   - alreadyInstalled — intelephense was already on PATH; nothing done.
     *   - installed        — npm install -g intelephense ran successfully.
     *   - skipped          — $skip was true; installation was explicitly opted out.
     *
     * @throws DevAiInstallException
     */
    public function ensure(bool $skip = false): EnsureResult
    {
        if ($skip) {
            return EnsureResult::skipped();
        }

        if ($this->runner->isOnPath('intelephense')) {
            return EnsureResult::alreadyInstalled();
        }

        if (!$this->runner->isOnPath('npm')) {
            throw DevAiInstallException::npmRequiredForLspDeps();
        }

        $result = $this->runner->run('npm', ['install', '-g', 'intelephense']);

        if (($result['exitCode'] ?? 1) !== 0) {
            throw DevAiInstallException::intelephenseInstallFailed($result['stderr'] ?? '');
        }

        return EnsureResult::installed();
    }
}
