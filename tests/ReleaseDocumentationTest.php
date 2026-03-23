<?php

declare(strict_types=1);

$docPath = dirname(__DIR__) . '/.claude/release-process.md';

it('creates .claude/release-process.md with complete release workflow', function () use ($docPath): void {
    expect(file_exists($docPath))->toBeTrue('.claude/release-process.md must exist');

    $content = file_get_contents($docPath);

    expect($content)
        ->toContain('# Release Process')
        ->toContain('## How It Works')
        ->toContain('## Cutting a Release')
        ->toContain('bin/release.sh')
        ->toContain('marko-php/marko');
});

it('documents the initial one-time setup process (org creation, repo creation, Packagist registration)', function () use ($docPath): void {
    $content = file_get_contents($docPath);

    expect($content)
        ->toContain('## Initial Setup')
        ->toContain('SPLIT_TOKEN')
        ->toContain('bin/create-split-repos.sh')
        ->toContain('bin/register-packagist.sh')
        ->toContain('marko-php');
});

it('documents the regular release process (merge to main, run release script)', function () use ($docPath): void {
    $content = file_get_contents($docPath);

    expect($content)
        ->toContain('git checkout main')
        ->toContain('git merge develop')
        ->toContain('./bin/release.sh')
        ->toContain('github.com/marko-php/marko/actions');
});

it('documents how to add a new package to the ecosystem', function () use ($docPath): void {
    $content = file_get_contents($docPath);

    expect($content)
        ->toContain('## Adding a New Package')
        ->toContain('bin/add-package.sh')
        ->toContain('PACKAGIST_USERNAME')
        ->toContain('PACKAGIST_TOKEN');
});

it('documents the branch strategy (develop, main, tags)', function () use ($docPath): void {
    $content = file_get_contents($docPath);

    expect($content)
        ->toContain('## Branch Strategy')
        ->toContain('develop')
        ->toContain('main')
        ->toContain('Tags');
});

it('documents versioning rules (unified semver, self.version, 0.x means unstable)', function () use ($docPath): void {
    $content = file_get_contents($docPath);

    expect($content)
        ->toContain('## Versioning')
        ->toContain('self.version')
        ->toContain('0.x')
        ->toContain('semver');
});

it('documents troubleshooting for common issues', function () use ($docPath): void {
    $content = file_get_contents($docPath);

    expect($content)
        ->toContain('## Troubleshooting')
        ->toContain('SPLIT_TOKEN')
        ->toContain('Packagist');
});
