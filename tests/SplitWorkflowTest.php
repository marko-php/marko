<?php

declare(strict_types=1);

$workflowPath = dirname(__DIR__) . '/.github/workflows/split.yml';
$workflowContent = file_exists($workflowPath) ? file_get_contents($workflowPath) : '';

it('creates a split workflow at .github/workflows/split.yml', function () use ($workflowPath): void {
    expect(file_exists($workflowPath))->toBeTrue();
});

it('triggers on tag push matching v* pattern', function () use ($workflowContent): void {
    expect($workflowContent)->toContain("tags: ['v*']");
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

it('uses SPLIT_TOKEN secret for authentication', function () use ($workflowContent): void {
    expect($workflowContent)->toContain('secrets.SPLIT_TOKEN')
        ->toContain('SPLIT_TOKEN');
});

it('configures the target organization as an environment variable for easy changes', function () use ($workflowContent): void {
    expect($workflowContent)->toContain('SPLIT_ORG: marko-php');
});
