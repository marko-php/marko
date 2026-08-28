<?php

declare(strict_types=1);

$ciPath = dirname(__DIR__) . '/.github/workflows/ci.yml';
$nightlyPath = dirname(__DIR__) . '/.github/workflows/nightly.yml';
$ci = file_exists($ciPath) ? file_get_contents($ciPath) : '';
$nightly = file_exists($nightlyPath) ? file_get_contents($nightlyPath) : '';

it('creates a CI workflow at .github/workflows/ci.yml', function () use ($ciPath): void {
    expect(file_exists($ciPath))->toBeTrue('.github/workflows/ci.yml must exist');
});

it('gates every pull request, not just those touching certain paths', function () use ($ci): void {
    // A paths: filter here would let a PR skip the gate entirely.
    $trigger = substr($ci, 0, (int) strpos($ci, 'jobs:'));

    expect($trigger)
        ->toContain('pull_request')
        ->not->toContain('paths:');
});

it('also runs on pushes to develop and main', function () use ($ci): void {
    expect($ci)
        ->toContain('branches:')
        ->toContain('- develop')
        ->toContain('- main');
});

it('runs tests, lint, and static analysis as separate jobs', function () use ($ci): void {
    expect($ci)
        ->toContain('name: Tests')
        ->toContain('name: Lint')
        ->toContain('name: Static analysis')
        ->toContain('composer test')
        ->toContain('phpcs --standard=phpcs.xml')
        ->toContain('php-cs-fixer fix --config=.php-cs-fixer.php --dry-run')
        ->toContain('composer phpstan');
});

it('pins PHP 8.5 in every job', function () use ($ci): void {
    expect(substr_count($ci, "php-version: '8.5'"))->toBe(substr_count($ci, 'runs-on: ubuntu-latest'));
});

it('pins actions to a major version rather than a floating ref', function () use ($ci, $nightly): void {
    preg_match_all('/uses: (\S+)/', $ci . $nightly, $matches);

    expect($matches[1])->not->toBeEmpty()
        ->and($matches[1])->each->toMatch('/@v\d+$/');
});

it('runs the destructive group on a schedule instead of on every PR', function () use ($ci, $nightly): void {
    expect($nightly)
        ->toContain('schedule:')
        ->toContain('cron:')
        ->toContain('composer test:all')
        ->and($ci)->toContain('composer test')
        ->and($ci)->not->toContain('test:all');
});

it('installs the roadrunner binary in the nightly workflow', function () use ($nightly): void {
    // Without this, packages/roadrunner's end-to-end suite finds no `rr`
    // binary on the nightly runner and every one of its security-critical
    // isolation assertions silently skips, defeating the plan's own stated
    // mitigation of running "locally and nightly" — this assertion exists
    // so a future edit cannot quietly drop the step and reintroduce that.
    $composer = json_decode(file_get_contents(dirname(__DIR__) . '/composer.json'), true);

    $dependenciesInstalledAt = strpos($nightly, 'ramsey/composer-install');
    $binaryInstalledAt = strpos($nightly, 'vendor/bin/rr get-binary');
    $testsRunAt = strpos($nightly, 'composer test:all');

    expect($composer['require-dev'])->toHaveKey('spiral/roadrunner-cli')
        ->and($dependenciesInstalledAt)->not->toBeFalse()
        ->and($binaryInstalledAt)->not->toBeFalse()
        ->and($testsRunAt)->not->toBeFalse()
        ->and($binaryInstalledAt)->toBeGreaterThan($dependenciesInstalledAt)
        ->and($binaryInstalledAt)->toBeLessThan($testsRunAt);
});

it('exposes composer phpstan and composer ci scripts the workflow depends on', function (): void {
    $composer = json_decode(file_get_contents(dirname(__DIR__) . '/composer.json'), true);

    expect($composer['scripts'])->toHaveKey('phpstan')
        ->and($composer['scripts'])->toHaveKey('ci')
        ->and($composer['scripts']['ci'])->toContain('@test')
        ->and($composer['scripts']['ci'])->toContain('@phpstan');
});

it('excludes deliberately-unparseable fixtures from both linters', function (): void {
    // php-cs-fixer lints before fixing and exits 4 on invalid PHP even with nothing to change;
    // phpcs aborts on the file. Either would fail the Lint job forever.
    $csFixer = file_get_contents(dirname(__DIR__) . '/.php-cs-fixer.php');
    $phpcs = file_get_contents(dirname(__DIR__) . '/phpcs.xml');

    $brokenFixtures = [
        'packages/config/tests/Unit/fixtures/syntax-error.php',
        'packages/codeindexer/tests/Fixtures/AttributeFixtures/src/Broken/SyntaxError.php',
        'packages/codeindexer/tests/Fixtures/ConfigFixtures/module-d/config/broken.php',
    ];

    foreach ($brokenFixtures as $fixture) {
        expect(file_exists(dirname(__DIR__) . '/' . $fixture))
            ->toBeTrue("$fixture should still exist")
            ->and($csFixer)->toContain(str_replace('packages/', '', $fixture));
    }

    expect($phpcs)->toContain('src/Broken/');
});

it('adds phpstan to the PR review checklist that previously omitted it', function (): void {
    $process = file_get_contents(dirname(__DIR__) . '/.claude/pr-review-process.md');

    expect($process)
        ->toContain('composer phpstan')
        ->toContain('PHPStan is not optional')
        ->not->toContain('No PR CI workflow exists in this repo');
});
