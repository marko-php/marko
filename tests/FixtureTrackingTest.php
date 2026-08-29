<?php

declare(strict_types=1);

namespace Marko\Tests;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Every file under a package's `tests/Fixtures/` must be reachable by git.
 *
 * Fixtures that model a real Marko project tree contain a directory literally
 * named `vendor/`, because that is what module discovery looks for. The root
 * `.gitignore` excludes `vendor/` with an unanchored pattern, which matches at
 * any depth — so without an explicit negation those fixture modules are never
 * committed. The failure is silent and local-only: the tests pass off untracked
 * files on the machine that wrote them, and the fixture has zero modules on a
 * fresh clone or in CI.
 *
 * This has already happened twice — once in `marko/roadrunner`, and latently in
 * `marko/codeindexer`, whose fixture files were tracked from before the rule
 * existed and would have vanished if any of them were ever re-added.
 */
function monorepoRoot(): string
{
    // tests/FixtureTrackingTest.php -> repository root.
    return dirname(__DIR__);
}

function fixtureFiles(): array
{
    $paths = [];

    foreach (glob(monorepoRoot() . '/packages/*/tests/Fixtures', GLOB_ONLYDIR) ?: [] as $fixtureRoot) {
        // Symlinks are deliberately not followed: the roadrunner fixture links
        // vendor/marko/roadrunner back at the package itself, so descending
        // through it would recurse without end.
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($fixtureRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isLink() || !$file->isFile()) {
                continue;
            }

            $paths[] = $file->getPathname();
        }
    }

    sort($paths);

    return $paths;
}

/**
 * @param list<string> $paths
 * @return list<string> the subset git would refuse to track
 */
function ignoredByGit(array $paths): array
{
    // --no-index also reports paths that are tracked today but would be ignored
    // if re-added, which is the latent form of this bug.
    $process = proc_open(
        ['git', 'check-ignore', '--stdin', '--no-index'],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        monorepoRoot(),
    );

    if (!is_resource($process)) {
        return [];
    }

    fwrite($pipes[0], implode("\n", $paths) . "\n");
    fclose($pipes[0]);

    $ignored = (string) stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);

    return array_values(array_filter(explode("\n", trim($ignored))));
}

it('keeps every package test fixture reachable by git', function (): void {
    $files = fixtureFiles();
    $ignored = ignoredByGit($files);

    expect($files)
        ->not->toBeEmpty()
        ->and($ignored)
        ->toBeEmpty(sprintf(
            'These fixture files are excluded by .gitignore, so they will not exist on a fresh '
                . "clone and the fixtures that depend on them will silently have nothing in them:\n  %s\n\n"
                . 'Add a negation to the root .gitignore re-including the path, the way '
                . '"!/packages/*/tests/Fixtures/**/vendor/" already does for fixture vendor directories.',
            implode("\n  ", $ignored),
        ));
});
