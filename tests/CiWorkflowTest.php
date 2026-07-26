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

    expect($matches[1])->not->toBeEmpty();

    foreach ($matches[1] as $action) {
        expect($action)->toMatch('/@v\d+$/');
    }
});

it('runs the destructive group on a schedule instead of on every PR', function () use ($ci, $nightly): void {
    expect($nightly)
        ->toContain('schedule:')
        ->toContain('cron:')
        ->toContain('composer test:all')
        ->and($ci)->toContain('composer test')
        ->and($ci)->not->toContain('test:all');
});

it('exposes composer phpstan and composer ci scripts the workflow depends on', function (): void {
    $composer = json_decode(file_get_contents(dirname(__DIR__) . '/composer.json'), true);

    expect($composer['scripts'])->toHaveKey('phpstan')
        ->and($composer['scripts'])->toHaveKey('ci')
        ->and($composer['scripts']['ci'])->toContain('@test')
        ->and($composer['scripts']['ci'])->toContain('@phpstan');
});

it('adds phpstan to the PR review checklist that previously omitted it', function (): void {
    $process = file_get_contents(dirname(__DIR__) . '/.claude/pr-review-process.md');

    expect($process)
        ->toContain('composer phpstan')
        ->toContain('PHPStan is not optional')
        ->not->toContain('No PR CI workflow exists in this repo');
});
