<?php

declare(strict_types=1);

namespace Marko\Skeleton\Prompts;

class PostCreateHook
{
    /** @var resource */
    private $errorStream;

    /**
     * @param resource|null $errorStream stream for error output (defaults to STDERR)
     */
    public function __construct(
        private DevAiPrompt $prompt,
        $errorStream = null,
    ) {
        $this->errorStream = $errorStream ?? STDERR;
    }

    /**
     * Run the post-create hook for $projectRoot.
     *
     * @param bool $interactive whether to prompt (false = use defaults)
     * @return int exit code
     */
    public function run(
        string $projectRoot,
        bool $interactive = true,
    ): int {
        if ($this->prompt->alreadyAnswered($projectRoot)) {
            return 0;
        }

        $accept = $interactive ? $this->prompt->ask() : true;
        $this->prompt->recordChoice($projectRoot, $accept);

        if (! $accept) {
            echo "Skipped marko/devai install.\n";
            echo "Next step: run `composer require --dev marko/devai && marko devai:install` later if you change your mind.\n";

            return 0;
        }

        $installResult = $this->prompt->install($projectRoot);
        if ($installResult !== 0) {
            fwrite($this->errorStream, "Failed to install marko/devai (exit code: $installResult)\n");
            fwrite(
                $this->errorStream,
                "marko/devai package was added to composer; you may try `marko devai:install` manually\n"
            );

            return $installResult;
        }

        $exitCode = $this->runDevaiInstall($projectRoot, $interactive);

        if ($exitCode !== 0) {
            fwrite($this->errorStream, "marko devai:install failed (exit code: $exitCode)\n");
            fwrite($this->errorStream, "marko/devai is installed. You can re-run `marko devai:install` manually.\n");

            return $exitCode;
        }

        return 0;
    }

    /**
     * Run `marko devai:install` as a subprocess.
     * Extracted for testability.
     */
    protected function runDevaiInstall(
        string $projectRoot,
        bool $interactive,
    ): int {
        $cmd = $interactive
            ? sprintf('cd %s && php marko devai:install 2>&1', escapeshellarg($projectRoot))
            : sprintf('cd %s && php marko devai:install --agents=claude-code 2>&1', escapeshellarg($projectRoot));
        passthru($cmd, $devaiInstallCode);

        return $devaiInstallCode;
    }
}
