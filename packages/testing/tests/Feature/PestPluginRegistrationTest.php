<?php

declare(strict_types=1);

/**
 * Runs Pest in a subprocess against tests/fixtures/pest-project, whose Pest.php
 * contains no require. The subprocess uses the monorepo's Composer install, where
 * marko/testing is a real dependency in vendor/ (path repository), so Composer's
 * autoload.files order and pestphp/pest-plugin's generated vendor/pest-plugins.json
 * are exactly what a user project gets.
 *
 * The child is a cold boot of the whole monorepo, so it runs once and every test in this
 * file reads the same result. Paratest hands a whole file to one worker, so that is one
 * child process per run, with or without --parallel.
 *
 * The child is not a paratest worker, so on exit Pest's Only plugin deletes
 * vendor/pestphp/pest/.temp/only.lock, which the parent run shares. That only matters
 * while someone has a local ->only(), which never reaches develop, so it is left alone.
 *
 * @return array{exitCode: int, output: string}
 */
function runFixturePestProject(): array
{
    static $result = null;

    if ($result !== null) {
        return $result;
    }

    $root = dirname(__DIR__, 4);
    $fixture = 'packages/testing/tests/fixtures/pest-project';
    // Unique per run: concurrent runs (worktrees, composer test alongside composer ci)
    // never share a result cache.
    $cacheDirectory = sys_get_temp_dir() . '/marko-testing-pest-fixture-' . bin2hex(random_bytes(8));

    $command = [
        PHP_BINARY,
        '-d',
        'memory_limit=2G',
        $root . '/vendor/bin/pest',
        '--configuration=' . $fixture . '/phpunit.xml',
        '--test-directory=' . $fixture . '/tests',
        '--colors=never',
        // Keeps PHPUnit from writing .phpunit.result.cache into the fixture directory.
        '--cache-directory=' . $cacheDirectory,
        '--do-not-cache-result',
    ];

    // The parent environment (TMPDIR and the rest) minus the variables that would make the
    // child believe it is a worker of the parent's parallel run.
    $environment = array_filter(
        getenv(),
        fn (string $value, string $name): bool => !in_array(
            $name,
            ['PARATEST', 'TEST_TOKEN', 'UNIQUE_TEST_TOKEN'],
            true,
        ) && !str_starts_with($name, 'PEST_PARALLEL'),
        ARRAY_FILTER_USE_BOTH,
    );

    // Keeps test names whole. Once a test takes 0.01s or more (common under parallel load),
    // Collision prints its duration and truncates the name to fit the terminal width,
    // which is 80 columns without a TTY, so the long test name loses its end.
    $environment['COLLISION_IGNORE_DURATION'] = 'true';

    try {
        $process = proc_open(
            $command,
            [1 => ['pipe', 'w'], 2 => ['redirect', 1]],
            $pipes,
            $root,
            $environment,
        );

        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start the fixture Pest process.');
        }

        $output = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $exitCode = proc_close($process);
    } finally {
        removeFixturePestCacheDirectory($cacheDirectory);
    }

    $result = ['exitCode' => $exitCode, 'output' => $output];

    return $result;
}

function removeFixturePestCacheDirectory(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }

    $entries = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($entries as $entry) {
        $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
    }

    rmdir($directory);
}

it('registers toHavePushed in a Pest run whose Pest.php has no require', function (): void {
    ['exitCode' => $exitCode, 'output' => $output] = runFixturePestProject();

    expect(str_contains($output, 'Call to undefined method Marko\Testing\Fake\FakeQueue::toHavePushed()'))
        ->toBeFalse($output)
        ->and(str_contains($output, 'passes toHavePushed when the job was pushed'))->toBeTrue($output)
        ->and($exitCode)->toBe(0, $output);
});

it('fails toHavePushed with an assertion failure when nothing was pushed', function (): void {
    ['exitCode' => $exitCode, 'output' => $output] = runFixturePestProject();

    expect(str_contains($output, 'fails toHavePushed with an assertion failure when nothing was pushed'))
        ->toBeTrue($output)
        ->and(str_contains($output, '3 passed'))->toBeTrue($output)
        ->and($exitCode)->toBe(0, $output);
});

it('shuts down an application a plain it() test booted, so the test is not risky', function (): void {
    ['exitCode' => $exitCode, 'output' => $output] = runFixturePestProject();

    expect(str_contains($output, 'boots an application without leaving its error handlers installed'))
        ->toBeTrue($output)
        ->and(str_contains($output, 'did not remove its own error handlers'))->toBeFalse($output)
        ->and(str_contains($output, 'did not remove its own exception handlers'))->toBeFalse($output)
        ->and(str_contains($output, 'risky'))->toBeFalse($output)
        ->and($exitCode)->toBe(0, $output);
});
