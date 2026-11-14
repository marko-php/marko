<?php

declare(strict_types=1);

namespace Marko\Tests\Support\Phpcs;

use RuntimeException;

/**
 * Runs the repository's real PHPCS, PHPCBF and php-cs-fixer against a fixture.
 *
 * Fixtures use the `.inc` extension so the pre-commit hook, the php-cs-fixer
 * Finder, Rector and Pest discovery all skip them. Each run copies the fixture
 * to a unique temp `.php` file (safe under `--parallel`), runs the tool on that
 * copy and removes it afterwards, so the fixture itself is never modified.
 */
readonly class PhpcsRunner
{
    private const array PARATEST_VARIABLES = ['PARATEST', 'TEST_TOKEN', 'UNIQUE_TEST_TOKEN'];

    private string $repoRoot;

    public function __construct()
    {
        $this->repoRoot = dirname(__DIR__, 3);
    }

    /**
     * Runs PHPCS and returns the messages it reports.
     *
     * The standard defaults to the repository's `phpcs.xml`; pass a path relative to the
     * repository root (e.g. `tools/phpcs/Marko`) to run another one.
     *
     * @return list<array{line: int, column: int, source: string, message: string, fixable: bool}>
     */
    public function sniff(
        string $fixturePath,
        ?string $sniffs = null,
        string $standard = 'phpcs.xml',
    ): array {
        return $this->withTempCopy($fixturePath, function (string $tempFile) use ($sniffs, $standard): array {
            $command = [
                $this->repoRoot . '/vendor/bin/phpcs',
                '--standard=' . $this->repoRoot . '/' . $standard,
                '--report=json',
                '-q',
                '--no-colors',
            ];

            if ($sniffs !== null) {
                $command[] = '--sniffs=' . $sniffs;
            }

            $command[] = $tempFile;

            $output = $this->run($command);
            $report = json_decode($output, true);

            if (
                !is_array($report)
                || !isset($report['files'][$tempFile]['messages'])
            ) {
                throw new RuntimeException("PHPCS did not return a JSON report for $tempFile:\n$output");
            }

            return array_map(
                fn (array $message): array => [
                    'line' => $message['line'],
                    'column' => $message['column'],
                    'source' => $message['source'],
                    'message' => $message['message'],
                    'fixable' => $message['fixable'],
                ],
                $report['files'][$tempFile]['messages'],
            );
        });
    }

    /**
     * Runs PHPCBF and returns the fixed source.
     *
     * The standard defaults to the repository's `phpcs.xml`; pass a path relative to the
     * repository root (e.g. `tools/phpcs/Marko`) to run another one.
     */
    public function fix(
        string $fixturePath,
        ?string $sniffs = null,
        string $standard = 'phpcs.xml',
    ): string {
        return $this->withTempCopy($fixturePath, function (string $tempFile) use ($sniffs, $standard): string {
            $command = [
                $this->repoRoot . '/vendor/bin/phpcbf',
                '--standard=' . $this->repoRoot . '/' . $standard,
                '--report=json',
                '-q',
                '--no-colors',
            ];

            if ($sniffs !== null) {
                $command[] = '--sniffs=' . $sniffs;
            }

            $command[] = $tempFile;

            // PHPCBF reports "No violations were found" on stderr for a clean file.
            $this->run($command, requireStdout: false);

            return $this->readFile($tempFile);
        });
    }

    /**
     * Runs php-cs-fixer with the repository's `.php-cs-fixer.php` and returns the fixed source.
     *
     * The cache is disabled because the shared `.php-cs-fixer.cache` races under `--parallel`.
     */
    public function fixWithPhpCsFixer(
        string $fixturePath,
    ): string {
        return $this->withTempCopy($fixturePath, function (string $tempFile): string {
            $this->run([
                $this->repoRoot . '/vendor/bin/php-cs-fixer',
                'fix',
                '--config=' . $this->repoRoot . '/.php-cs-fixer.php',
                '--using-cache=no',
                '--quiet',
                $tempFile,
            ]);

            return $this->readFile($tempFile);
        });
    }

    /**
     * @template T
     *
     * @param callable(string): T $callback
     *
     * @return T
     */
    private function withTempCopy(
        string $fixturePath,
        callable $callback,
    ): mixed {
        $source = $this->readFile($fixturePath);
        // PHPCS reports the canonical path (e.g. macOS's /private/var), so resolve it up front.
        $tempDir = realpath(sys_get_temp_dir()) . '/marko-phpcs-' . bin2hex(random_bytes(8));

        if (
            !mkdir($tempDir, 0700, true)
            && !is_dir($tempDir)
        ) {
            throw new RuntimeException("Could not create temp directory $tempDir");
        }

        $tempFile = $tempDir . '/' . pathinfo($fixturePath, PATHINFO_FILENAME) . '.php';

        try {
            if (file_put_contents($tempFile, $source) === false) {
                throw new RuntimeException("Could not write temp fixture $tempFile");
            }

            return $callback($tempFile);
        } finally {
            if (is_file($tempFile)) {
                unlink($tempFile);
            }

            rmdir($tempDir);
        }
    }

    /**
     * Runs a command from the repository root and returns its stdout.
     *
     * The exit code is ignored on purpose: PHPCS and PHPCBF exit codes are a bitmask
     * that is non-zero whenever violations exist or were fixed.
     *
     * Output written only to stderr means the tool failed, unless `requireStdout` is false.
     *
     * @param list<string> $command
     */
    private function run(
        array $command,
        bool $requireStdout = true,
    ): string {
        $process = proc_open(
            $command,
            [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $this->repoRoot,
            $this->environment(),
        );

        if (!is_resource($process)) {
            throw new RuntimeException('Could not start ' . $command[0]);
        }

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        if (
            $requireStdout
            && $stdout === ''
            && $stderr !== ''
        ) {
            throw new RuntimeException('Command ' . $command[0] . " wrote only to stderr:\n$stderr");
        }

        return $stdout;
    }

    /**
     * The parent environment minus the paratest variables, so the child is not a worker of the parent run.
     *
     * @return array<string, string>
     */
    private function environment(): array
    {
        return array_filter(
            getenv(),
            fn (string $name): bool => !in_array($name, self::PARATEST_VARIABLES, true)
                && !str_starts_with($name, 'PEST_PARALLEL'),
            ARRAY_FILTER_USE_KEY,
        );
    }

    private function readFile(
        string $path,
    ): string {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Could not read $path");
        }

        return $contents;
    }
}
