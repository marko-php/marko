<?php

declare(strict_types=1);

$packagesRoot = dirname(__DIR__) . '/packages';

$packages = array_values(array_filter(
    scandir($packagesRoot),
    fn(string $entry): bool => $entry !== '.' && $entry !== '..' && is_dir($packagesRoot . '/' . $entry),
));

it('creates .gitattributes in all 71 package directories', function () use ($packagesRoot, $packages): void {
    expect($packages)->toHaveCount(71);

    foreach ($packages as $package) {
        $path = $packagesRoot . '/' . $package . '/.gitattributes';
        expect(file_exists($path))->toBeTrue("Missing .gitattributes in packages/{$package}");
    }
});

it('excludes tests/ directory from exports', function () use ($packagesRoot, $packages): void {
    foreach ($packages as $package) {
        $path = $packagesRoot . '/' . $package . '/.gitattributes';
        $content = file_get_contents($path);
        expect($content)->toContain('/tests')
            ->toContain('export-ignore');
    }
});

it('excludes .gitattributes itself from exports', function () use ($packagesRoot, $packages): void {
    foreach ($packages as $package) {
        $path = $packagesRoot . '/' . $package . '/.gitattributes';
        $content = file_get_contents($path);
        expect($content)->toContain('/.gitattributes')
            ->toContain('export-ignore');
    }
});

it('excludes .gitignore from exports if present', function () use ($packagesRoot, $packages): void {
    foreach ($packages as $package) {
        $path = $packagesRoot . '/' . $package . '/.gitattributes';
        $content = file_get_contents($path);
        expect($content)->toContain('/.gitignore')
            ->toContain('export-ignore');
    }
});

it('excludes phpunit.xml or phpunit.xml.dist from exports if present', function () use ($packagesRoot, $packages): void {
    foreach ($packages as $package) {
        $path = $packagesRoot . '/' . $package . '/.gitattributes';
        $content = file_get_contents($path);
        $hasPhpunit = str_contains($content, '/phpunit.xml');
        expect($hasPhpunit)->toBeTrue("packages/{$package}/.gitattributes missing phpunit.xml export-ignore");
    }
});

it('creates or updates root .gitattributes for the monorepo', function (): void {
    $rootPath = dirname(__DIR__) . '/.gitattributes';
    expect(file_exists($rootPath))->toBeTrue('Root .gitattributes should exist');

    $content = file_get_contents($rootPath);
    expect($content)->toContain('text=auto')
        ->toContain('*.php')
        ->toContain('eol=lf');
});

it('creates LICENSE (MIT) in all 71 package directories with copyright Devtomic LLC', function () use ($packagesRoot, $packages): void {
    expect($packages)->toHaveCount(71);

    foreach ($packages as $package) {
        $path = $packagesRoot . '/' . $package . '/LICENSE';
        expect(file_exists($path))->toBeTrue("Missing LICENSE in packages/{$package}");

        $content = file_get_contents($path);
        expect($content)->toContain('MIT License')
            ->toContain('Copyright (c) Devtomic LLC')
            ->toContain('Permission is hereby granted');
    }
});

it('has package options in bug report issue template', function () use ($packagesRoot, $packages): void {
    $templatePath = $packagesRoot . '/../.github/ISSUE_TEMPLATE/bug_report.yml';
    $content = file_get_contents($templatePath);
    preg_match_all('/^        - (.+)$/m', $content, $matches);
    $options = $matches[1];

    foreach ($packages as $package) {
        expect($options)->toContain($package);
    }
});

it('has package options in feature request issue template', function () use ($packagesRoot, $packages): void {
    $templatePath = $packagesRoot . '/../.github/ISSUE_TEMPLATE/feature_request.yml';
    $content = file_get_contents($templatePath);
    preg_match_all('/^        - (.+)$/m', $content, $matches);
    $options = $matches[1];

    foreach ($packages as $package) {
        expect($options)->toContain($package);
    }
});

it('updates the root LICENSE file with copyright Devtomic LLC', function (): void {
    $rootPath = dirname(__DIR__) . '/LICENSE';
    expect(file_exists($rootPath))->toBeTrue('Root LICENSE should exist');

    $content = file_get_contents($rootPath);
    expect($content)->toContain('MIT License')
        ->toContain('Copyright (c) Devtomic LLC')
        ->toContain('Permission is hereby granted');
});
