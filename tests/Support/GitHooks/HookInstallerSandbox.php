<?php

declare(strict_types=1);

namespace Marko\Tests\Support\GitHooks;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * A throwaway directory for running `tools/install-git-hooks.php` against temp git repositories.
 *
 * Git runs with no global or system config and cannot look above the sandbox, so neither the
 * real repository nor the user's own git config can leak into a test.
 */
readonly class HookInstallerSandbox
{
    private function __construct(
        private string $repoRoot,
        private string $path,
    ) {}

    public static function create(): self
    {
        $path = realpath(sys_get_temp_dir()) . '/marko-hook-installer-' . bin2hex(random_bytes(8));
        $sandbox = new self(dirname(__DIR__, 3), $path);
        $sandbox->makeDirectory($path);

        return $sandbox;
    }

    public function path(
        string $relativePath = '',
    ): string {
        return $relativePath === '' ? $this->path : $this->path . '/' . $relativePath;
    }

    /**
     * Creates a git repository at the relative path, optionally with a `.githooks/pre-commit`.
     */
    public function initRepository(
        string $relativePath,
        bool $withPreCommitHook = true,
    ): string {
        $repository = $this->path($relativePath);
        $this->makeDirectory($repository);
        $this->mustRun(['git', 'init', '--quiet'], $repository);

        if ($withPreCommitHook) {
            $this->addPreCommitHook($relativePath);
        }

        return $repository;
    }

    public function addPreCommitHook(
        string $relativePath,
    ): void {
        $this->makeDirectory($this->path($relativePath) . '/.githooks');

        if (file_put_contents($this->path($relativePath) . '/.githooks/pre-commit', "#!/bin/bash\n") === false) {
            throw new RuntimeException("Could not write the pre-commit hook in $relativePath");
        }
    }

    public function setHooksPath(
        string $repository,
        string $hooksPath,
    ): void {
        $this->mustRun(['git', 'config', '--local', 'core.hooksPath', $hooksPath], $repository);
    }

    /**
     * The repository's local core.hooksPath, or null when it is unset.
     */
    public function hooksPath(
        string $repository,
    ): ?string {
        $result = $this->run(['git', 'config', '--local', '--get', 'core.hooksPath'], $repository);

        return $result['exitCode'] === 0 ? trim($result['output']) : null;
    }

    /**
     * Runs the hook installer for the project root from inside the sandbox.
     *
     * @param array<string, string> $environment Variables to override in the installer's environment
     *
     * @return array{exitCode: int, output: string}
     */
    public function runInstaller(
        string $projectRoot,
        array $environment = [],
    ): array {
        return $this->run(
            [PHP_BINARY, $this->repoRoot . '/tools/install-git-hooks.php', $projectRoot],
            $this->path,
            $environment,
        );
    }

    public function remove(): void
    {
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            if (
                $item->isDir()
                && !$item->isLink()
            ) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($this->path);
    }

    /**
     * @param list<string> $command
     * @param array<string, string> $environment
     *
     * @return array{exitCode: int, output: string}
     */
    private function run(
        array $command,
        string $workingDirectory,
        array $environment = [],
    ): array {
        $process = proc_open(
            $command,
            [
                1 => ['pipe', 'w'],
                2 => ['redirect', 1],
            ],
            $pipes,
            $workingDirectory,
            [...$this->environment(), ...$environment],
        );

        if (!is_resource($process)) {
            throw new RuntimeException('Could not start ' . implode(' ', $command));
        }

        $output = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        return [
            'exitCode' => proc_close($process),
            'output' => $output,
        ];
    }

    /**
     * @param list<string> $command
     */
    private function mustRun(
        array $command,
        string $workingDirectory,
    ): void {
        $result = $this->run($command, $workingDirectory);

        if ($result['exitCode'] !== 0) {
            throw new RuntimeException(implode(' ', $command) . " failed in $workingDirectory:\n" . $result['output']);
        }
    }

    /**
     * The parent environment minus the paratest and git variables, with git isolated from the
     * user's config and from any repository above the sandbox.
     *
     * @return array<string, string>
     */
    private function environment(): array
    {
        $environment = array_filter(
            getenv(),
            fn (string $name): bool => !in_array($name, ['PARATEST', 'TEST_TOKEN', 'UNIQUE_TEST_TOKEN'], true)
                && !str_starts_with($name, 'PEST_PARALLEL')
                && !str_starts_with($name, 'GIT_'),
            ARRAY_FILTER_USE_KEY,
        );

        return [
            ...$environment,
            'GIT_CONFIG_GLOBAL' => '/dev/null',
            'GIT_CONFIG_NOSYSTEM' => '1',
            'GIT_CEILING_DIRECTORIES' => dirname($this->path),
        ];
    }

    private function makeDirectory(
        string $path,
    ): void {
        if (
            !is_dir($path)
            && !mkdir($path, 0700, true)
            && !is_dir($path)
        ) {
            throw new RuntimeException("Could not create directory $path");
        }
    }
}
