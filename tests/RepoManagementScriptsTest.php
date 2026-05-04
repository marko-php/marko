<?php

declare(strict_types=1);

$binDir = dirname(__DIR__) . '/bin';

it(
    'creates bin/create-split-repos.sh that bulk-creates all 70 split repos on GitHub',
    function () use ($binDir): void {
        $path = $binDir . '/create-split-repos.sh';
    
        expect(file_exists($path))->toBeTrue('bin/create-split-repos.sh does not exist');
    
        $content = file_get_contents($path);
    
        expect($content)
            ->toContain('#!/usr/bin/env bash')
            ->toContain('GITHUB_ORG=')
            ->toContain('gh repo create')
            ->toContain('packages/');
    }
);

it('creates bin/register-packagist.sh that registers all 70 packages on Packagist', function () use ($binDir): void {
    $path = $binDir . '/register-packagist.sh';

    expect(file_exists($path))->toBeTrue('bin/register-packagist.sh does not exist');

    $content = file_get_contents($path);

    expect($content)
        ->toContain('#!/usr/bin/env bash')
        ->toContain('PACKAGIST_USERNAME')
        ->toContain('PACKAGIST_TOKEN')
        ->toContain('packagist.org/api/create-package')
        ->toContain('packages/');
});

it('creates bin/add-package.sh that handles the full new-package workflow', function () use ($binDir): void {
    $path = $binDir . '/add-package.sh';

    expect(file_exists($path))->toBeTrue('bin/add-package.sh does not exist');

    $content = file_get_contents($path);

    expect($content)
        ->toContain('#!/usr/bin/env bash')
        ->toContain('GITHUB_ORG=')
        ->toContain('PACKAGIST_USERNAME')
        ->toContain('PACKAGIST_TOKEN')
        ->toContain('gh repo create')
        ->toContain('packagist.org/api/create-package')
        ->toContain('composer.json');
});

it('dynamically reads package list from packages/ directory', function () use ($binDir): void {
    $createRepos = file_get_contents($binDir . '/create-split-repos.sh');
    $registerPackagist = file_get_contents($binDir . '/register-packagist.sh');

    // Scripts iterate over packages/ directory dynamically, not a hardcoded list
    expect($createRepos)
        ->toContain('packages/*/')
        ->toContain('for pkg_dir in')
        ->and($registerPackagist)
        ->toContain('packages/*/')
        ->toContain('for pkg_dir in');
});

it('detects existing repos without erroring', function () use ($binDir): void {
    $createRepos = file_get_contents($binDir . '/create-split-repos.sh');
    $registerPackagist = file_get_contents($binDir . '/register-packagist.sh');
    $addPackage = file_get_contents($binDir . '/add-package.sh');

    // create-split-repos.sh checks existing repos before creating
    expect($createRepos)
        ->toContain('gh repo list')
        ->toContain('EXISTING_REPOS')
        ->toContain('already existed')
        // register-packagist.sh handles HTTP 400 (already registered) gracefully
        ->and($registerPackagist)
        ->toContain('"400"')
        ->toContain('skipping')
        // add-package.sh checks if split repo already exists
        ->and($addPackage)
        ->toContain('gh repo view')
        ->toContain('already exists');
});

it(
    'validates required tools and credentials before running (gh, curl, jq, API tokens)',
    function () use ($binDir): void {
        $createRepos = file_get_contents($binDir . '/create-split-repos.sh');
        $registerPackagist = file_get_contents($binDir . '/register-packagist.sh');
        $addPackage = file_get_contents($binDir . '/add-package.sh');
    
        // create-split-repos.sh validates gh, jq, and gh auth
    expect($createRepos)
            ->toContain('command -v gh')
            ->toContain('command -v jq')
            ->toContain('gh auth status')
            // register-packagist.sh validates curl, jq, and requires API tokens
        ->and($registerPackagist)
            ->toContain('command -v curl')
            ->toContain('command -v jq')
            ->toContain('PACKAGIST_USERNAME:?')
            ->toContain('PACKAGIST_TOKEN:?')
            // add-package.sh validates gh, jq, curl, and requires API tokens
        ->and($addPackage)
            ->toContain('command -v gh')
            ->toContain('command -v jq')
            ->toContain('command -v curl')
            ->toContain('PACKAGIST_USERNAME:?')
            ->toContain('PACKAGIST_TOKEN:?');
    }
);

it('makes all scripts executable', function () use ($binDir): void {
    $scripts = [
        $binDir . '/create-split-repos.sh',
        $binDir . '/register-packagist.sh',
        $binDir . '/add-package.sh',
    ];

    foreach ($scripts as $script) {
        expect(is_executable($script))->toBeTrue("Script not executable: $script");
    }
});
