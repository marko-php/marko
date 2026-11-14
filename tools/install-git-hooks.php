#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Points git at the repository's `.githooks` directory, so `.githooks/pre-commit` runs on every commit.
 *
 * Composer runs it from `post-install-cmd` and `post-update-cmd`.
 *
 * Usage:
 *   php tools/install-git-hooks.php [project-root]
 */

$projectRoot = $argv[1] ?? getcwd();

if (!is_file($projectRoot . '/.githooks/pre-commit')) {
    echo "$projectRoot has no .githooks/pre-commit, so no hook was installed.\n";

    exit(0);
}

/**
 * Runs git in the project root and returns its exit code and combined output.
 *
 * @param list<string> $arguments
 *
 * @return array{exitCode: int, output: string}
 *
 * @throws RuntimeException When git cannot be started
 */
function runGit(
    string $projectRoot,
    array $arguments,
): array {
    $startError = '';
    set_error_handler(function (int $level, string $message) use (&$startError): bool {
        $startError = $message;

        return true;
    });

    try {
        $process = proc_open(
            ['git', ...$arguments],
            [
                1 => ['pipe', 'w'],
                2 => ['redirect', 1],
            ],
            $pipes,
            $projectRoot,
        );
    } finally {
        restore_error_handler();
    }

    if (!is_resource($process)) {
        throw new RuntimeException($startError);
    }

    $output = (string) stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    return [
        'exitCode' => proc_close($process),
        'output' => trim($output),
    ];
}

/**
 * Whether a core.hooksPath value names git's default hooks directory, which behaves exactly like no setting.
 *
 * @throws RuntimeException When git cannot be started
 */
function isDefaultHooksDirectory(
    string $projectRoot,
    string $hooksPath,
): bool {
    $commonDirectory = runGit($projectRoot, ['rev-parse', '--path-format=absolute', '--git-common-dir']);
    $resolvedHooksPath = realpath(str_starts_with($hooksPath, '/') ? $hooksPath : $projectRoot . '/' . $hooksPath);

    return $resolvedHooksPath !== false
        && $resolvedHooksPath === realpath($commonDirectory['output'] . '/hooks');
}

try {
    $topLevel = runGit($projectRoot, ['rev-parse', '--show-toplevel']);
} catch (RuntimeException $exception) {
    fwrite(
        STDERR,
        "Warning: git could not run ({$exception->getMessage()}), so the pre-commit hook was not installed.\n",
    );

    exit(0);
}

if (
    $topLevel['exitCode'] !== 0
    || realpath($topLevel['output']) !== realpath($projectRoot)
) {
    echo "$projectRoot is not a git checkout, so the pre-commit hook was not installed.\n";

    exit(0);
}

$current = runGit($projectRoot, ['config', '--local', '--get', 'core.hooksPath']);

if (
    $current['exitCode'] === 0
    && $current['output'] !== '.githooks'
    && !isDefaultHooksDirectory($projectRoot, $current['output'])
) {
    fwrite(
        STDERR,
        "Warning: core.hooksPath is already set to '{$current['output']}', so the pre-commit hook was not installed. "
        . "Run `git config core.hooksPath .githooks` to use it.\n",
    );

    exit(0);
}

runGit($projectRoot, ['config', '--local', 'core.hooksPath', '.githooks']);
