<?php

declare(strict_types=1);

namespace Marko\Tests\Support\Phpcs;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * A throwaway git repository that runs the repository's real `.githooks/pre-commit`.
 *
 * It holds copies of the hook and every config file the hook reads, plus a symlink to
 * the real `vendor/`, so a test can stage a file, run the hook exactly as git would,
 * and read back what ended up staged.
 */
readonly class PreCommitRepository
{
    public const string STAGED_PATH = 'packages/fixture/src/Combined.php';

    private const array COPIED_PATHS = [
        '.githooks/pre-commit',
        '.php-cs-fixer.php',
        'dev/php-cs-fixer',
        'phpcs.xml',
        'rector.php',
        'tools/phpcs',
        'tools/fix-multiline-params.php',
    ];

    private function __construct(
        private string $repoRoot,
        private string $path,
    ) {}

    public static function create(): self
    {
        $repoRoot = dirname(__DIR__, 3);
        $path = realpath(sys_get_temp_dir()) . '/marko-pre-commit-' . bin2hex(random_bytes(8));
        $repository = new self($repoRoot, $path);

        $repository->makeDirectory($path);
        $repository->mustRun(['git', 'init', '--quiet']);

        if (!symlink($repoRoot . '/vendor', $path . '/vendor')) {
            throw new RuntimeException("Could not link vendor/ into $path");
        }

        foreach (self::COPIED_PATHS as $copiedPath) {
            $repository->copy($repoRoot . '/' . $copiedPath, $path . '/' . $copiedPath);
        }

        return $repository;
    }

    public function stage(
        string $fixturePath,
    ): void {
        $this->copy($fixturePath, $this->path . '/' . self::STAGED_PATH);
        $this->mustRun(['git', 'add', self::STAGED_PATH]);
    }

    /**
     * Runs the pre-commit hook and returns its exit code and output.
     *
     * @return array{exitCode: int, output: string}
     */
    public function runHook(): array
    {
        return $this->run(['bash', '.githooks/pre-commit']);
    }

    public function stagedContents(): string
    {
        return $this->mustRun(['git', 'show', ':' . self::STAGED_PATH]);
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
     * Runs a command from the repository's root and returns its exit code and output.
     *
     * @param list<string> $command
     *
     * @return array{exitCode: int, output: string}
     */
    public function run(
        array $command,
    ): array {
        $process = proc_open(
            $command,
            [
                1 => ['pipe', 'w'],
                2 => ['redirect', 1],
            ],
            $pipes,
            $this->path,
            $this->environment(),
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
    ): string {
        $result = $this->run($command);

        if ($result['exitCode'] !== 0) {
            throw new RuntimeException(implode(' ', $command) . " failed in $this->path:\n" . $result['output']);
        }

        return $result['output'];
    }

    /**
     * The parent environment minus the paratest variables and any git variables that would
     * point the child at another repository.
     *
     * @return array<string, string>
     */
    private function environment(): array
    {
        return array_filter(
            getenv(),
            fn (string $name): bool => !in_array($name, ['PARATEST', 'TEST_TOKEN', 'UNIQUE_TEST_TOKEN'], true)
                && !str_starts_with($name, 'PEST_PARALLEL')
                && !str_starts_with($name, 'GIT_'),
            ARRAY_FILTER_USE_KEY,
        );
    }

    private function copy(
        string $source,
        string $destination,
    ): void {
        if (is_dir($source)) {
            $items = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST,
            );

            $this->makeDirectory($destination);

            foreach ($items as $item) {
                $target = $destination . substr($item->getPathname(), strlen($source));

                if ($item->isDir()) {
                    $this->makeDirectory($target);
                } else {
                    $this->copy($item->getPathname(), $target);
                }
            }

            return;
        }

        $this->makeDirectory(dirname($destination));

        if (!copy($source, $destination)) {
            throw new RuntimeException("Could not copy $source to $destination");
        }
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
