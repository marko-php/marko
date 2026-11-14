<?php

declare(strict_types=1);

namespace Marko\DevAi\Installation;

use Marko\DevAi\Exceptions\DevAiInstallException;
use Marko\DevAi\Process\CommandRunnerInterface;

class IntelephenseEnsurer implements IntelephenseEnsurerInterface
{
    /**
     * The exact intelephense release installed globally. Pinned so a compromised or
     * broken upstream release is never pulled in automatically; bump deliberately.
     */
    public const string VERSION = '1.18.5';

    public const string PACKAGE = 'intelephense@' . self::VERSION;

    public function __construct(private CommandRunnerInterface $runner) {}

    /**
     * Ensure intelephense is available globally via npm.
     *
     * Returns an EnsureResult describing what happened:
     *   - alreadyInstalled — intelephense was already on PATH; nothing done.
     *   - installed        — npm install -g intelephense@VERSION ran successfully.
     *   - skipped          — $skip was true; installation was explicitly opted out.
     *
     * @throws DevAiInstallException
     */
    public function ensure(
        bool $skip = false,
    ): EnsureResult {
        if ($skip) {
            return EnsureResult::skipped();
        }

        if ($this->runner->isOnPath('intelephense')) {
            return EnsureResult::alreadyInstalled();
        }

        if (!$this->runner->isOnPath('npm')) {
            throw DevAiInstallException::npmRequiredForLspDeps();
        }

        $result = $this->runner->run('npm', ['install', '-g', self::PACKAGE]);

        if (($result['exitCode'] ?? 1) !== 0) {
            throw DevAiInstallException::intelephenseInstallFailed($result['stderr'] ?? '');
        }

        return EnsureResult::installed();
    }
}
