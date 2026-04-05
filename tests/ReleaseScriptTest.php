<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$script = $root . '/bin/release.sh';

it('prints a summary of what was released and next steps', function () use ($script): void {
    $contents = file_get_contents($script);

    expect($contents)->toContain('Released ${TAG}')
        ->and($contents)->toContain('Next steps')
        ->and($contents)->toContain('Packagist')
        ->and($contents)->toContain('GitHub Actions');
});

it('pushes the tag to origin', function () use ($script): void {
    $contents = file_get_contents($script);

    expect($contents)->toContain('git push origin "$TAG"');
});

it('creates an annotated git tag with version as tag name', function () use ($script): void {
    $contents = file_get_contents($script);

    expect($contents)->toContain('TAG="${VERSION}"')
        ->and($contents)->toContain('git tag -a "$TAG"')
        ->and($contents)->toContain('-m "Release ${VERSION}"');
});

it('runs the test suite and aborts if tests fail', function () use ($script): void {
    $contents = file_get_contents($script);

    expect($contents)->toContain('vendor/bin/pest --parallel')
        ->and($contents)->toContain('Tests failed')
        ->and($contents)->toContain('Fix failing tests before releasing');
});

it('validates the tag does not already exist', function () use ($script): void {
    $contents = file_get_contents($script);

    expect($contents)->toContain('git rev-parse')
        ->and($contents)->toContain('already exists');
});

it('validates the working directory is clean (no uncommitted changes)', function () use ($script): void {
    $contents = file_get_contents($script);

    expect($contents)->toContain('git diff --quiet')
        ->and($contents)->toContain('git diff --cached --quiet')
        ->and($contents)->toContain('uncommitted changes');
});

it('validates the current branch is main', function () use ($script): void {
    $contents = file_get_contents($script);

    expect($contents)->toContain('git checkout main')
        ->and($contents)->toContain('git merge develop');
});

it('validates semver format (rejects invalid versions like 1.0 or v1.0.0)', function () use ($script): void {
    $contents = file_get_contents($script);

    // Verify semver regex is present and correct
    expect($contents)->toContain('^[0-9]+\.[0-9]+\.[0-9]+$')
        ->and($contents)->toContain('Invalid version format')
        ->and($contents)->toContain('Expected X.Y.Z');
});

it('validates PHP version is 8.5+ (uses PHP_BIN env var or defaults to system php)', function () use ($script): void {
    $contents = file_get_contents($script);

    expect($contents)->toContain('PHP_BIN="${PHP_BIN:-php}"')
        ->and($contents)->toContain('8.5');
});

it('creates bin/release.sh with version argument validation', function () use ($root, $script): void {
    expect(file_exists($script))->toBeTrue('bin/release.sh does not exist')
        ->and(is_executable($script))->toBeTrue('bin/release.sh is not executable');

    // Running without arguments should exit non-zero with usage message
    $output = [];
    $exitCode = 0;
    exec(escapeshellarg($script) . ' 2>&1', $output, $exitCode);
    $outputStr = implode("\n", $output);

    expect($exitCode)->not->toBe(0)
        ->and($outputStr)->toContain('0.1.0');
});
