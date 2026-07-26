<?php

declare(strict_types=1);

$workflowPath = dirname(__DIR__) . '/.github/workflows/split.yml';
$workflowContent = file_exists($workflowPath) ? file_get_contents($workflowPath) : '';

it('creates a split workflow at .github/workflows/split.yml', function () use ($workflowPath): void {
    expect(file_exists($workflowPath))->toBeTrue();
});

it('triggers on tag push matching semver pattern', function () use ($workflowContent): void {
    expect($workflowContent)->toContain("tags: ['[0-9]*']");
});

it('triggers on push to main and develop branches', function () use ($workflowContent): void {
    expect($workflowContent)->toContain('branches: [main, develop]');
});

it('dynamically discovers packages from the packages directory', function () use ($workflowContent): void {
    expect($workflowContent)->toContain('ls packages/');
});

it('splits each package subdirectory to its own repository', function () use ($workflowContent): void {
    expect($workflowContent)->toContain('splitsh-lite')
        ->toContain('packages/${{ matrix.package }}');
});

it('tags each split repository with the same version tag on tag push', function () use ($workflowContent): void {
    expect($workflowContent)->toContain('refs/tags/')
        ->toContain('refs/tags/${TAG}');
});

it('pushes branch updates to split repos on branch push', function () use ($workflowContent): void {
    expect($workflowContent)->toContain('refs/heads/${BRANCH}');
});

it('uses MARKO_BUILD_PAT secret for authentication', function () use ($workflowContent): void {
    expect($workflowContent)->toContain('secrets.MARKO_BUILD_PAT')
        ->toContain('MARKO_BUILD_PAT');
});

it('configures the target organization as an environment variable for easy changes', function () use ($workflowContent): void {
    expect($workflowContent)->toContain('SPLIT_ORG: marko-php');
});

it('retries the Packagist update on transient upstream failures', function () use ($workflowContent): void {
    // All 92 split jobs POST to Packagist within the same second, so a release
    // reliably trips its rate limits. 0.8.5 lost 8 packages to a burst of 500s
    // that the single un-retried curl surfaced as a hard job failure.
    expect($workflowContent)->toContain('update_with_retry');
});

it('treats 5xx, 429, and curl transport errors as retryable', function () use ($workflowContent): void {
    expect($workflowContent)->toContain('000|429|5??)');
});

it('backs off between Packagist retries with jitter so they do not re-collide', function () use ($workflowContent): void {
    // Every job fails at the same instant, so a fixed backoff would just line
    // the retries up again on the same second.
    expect($workflowContent)->toContain('RANDOM')
        ->toContain('sleep "$delay"');
});

it('still self-heals an unregistered package via create-package on 404', function () use ($workflowContent): void {
    expect($workflowContent)->toContain('api/create-package')
        ->toContain('"$HTTP" == "404"');
});

it('fails the job on any non-2xx Packagist response once retries are exhausted', function () use ($workflowContent): void {
    // Retrying means the curl can no longer abort the step itself, so a
    // transport failure arrives here as 000 — numerically zero, and waved
    // through by a `-ge 400` test as if it had succeeded.
    expect($workflowContent)->toContain('2??)')
        ->toContain('exit 1');
});

it('clears the response file between attempts', function () use ($workflowContent): void {
    // curl leaves it untouched when it cannot connect, which otherwise reports
    // the previous attempt's body as though it belonged to this one.
    expect($workflowContent)->toContain(': > "$RESP_FILE"');
});
