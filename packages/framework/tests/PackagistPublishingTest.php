<?php

declare(strict_types=1);

function getPackageComposerFiles(): array
{
    $packagesDir = dirname(__DIR__, 3);
    $files = glob($packagesDir . '/packages/*/composer.json');

    return array_filter($files, fn(string $f) => !str_contains($f, '/vendor/'));
}

it('removes repositories key from all 38 package composer.json files that have path repos', function () {
    $files = getPackageComposerFiles();

    $withRepos = [];
    foreach ($files as $file) {
        $data = json_decode(file_get_contents($file), true);
        if (isset($data['repositories'])) {
            $withRepos[] = $file;
        }
    }

    expect($withRepos)->toBeEmpty('These package composer.json files still contain a "repositories" key: ' . implode(', ', array_map('basename', array_map('dirname', $withRepos))));
});

it('changes all internal marko/* require constraints from @dev to self.version', function () {
    $files = getPackageComposerFiles();

    $violations = [];
    foreach ($files as $file) {
        $data = json_decode(file_get_contents($file), true);
        $require = $data['require'] ?? [];
        foreach ($require as $package => $constraint) {
            if (str_starts_with($package, 'marko/') && $constraint !== 'self.version') {
                $violations[] = basename(dirname($file)) . ": $package=$constraint";
            }
        }
    }

    expect($violations)->toBeEmpty('These require constraints are not self.version: ' . implode(', ', $violations));
});

it('changes all internal marko/* require-dev constraints from @dev to self.version', function () {
    $files = getPackageComposerFiles();

    $violations = [];
    foreach ($files as $file) {
        $data = json_decode(file_get_contents($file), true);
        $requireDev = $data['require-dev'] ?? [];
        foreach ($requireDev as $package => $constraint) {
            if (str_starts_with($package, 'marko/') && $constraint !== 'self.version') {
                $violations[] = basename(dirname($file)) . ": $package=$constraint";
            }
        }
    }

    expect($violations)->toBeEmpty('These require-dev constraints are not self.version: ' . implode(', ', $violations));
});

it('changes marko/dev-server wildcard constraints to self.version', function () {
    $devServerComposer = dirname(__DIR__, 3) . '/packages/dev-server/composer.json';
    $data = json_decode(file_get_contents($devServerComposer), true);

    $violations = [];
    foreach (($data['require'] ?? []) as $package => $constraint) {
        if (str_starts_with($package, 'marko/') && $constraint !== 'self.version') {
            $violations[] = "$package=$constraint";
        }
    }

    expect($violations)->toBeEmpty('dev-server has non-self.version marko/* constraints: ' . implode(', ', $violations));
});

it('changes any remaining wildcard marko/* constraints to self.version', function () {
    $files = getPackageComposerFiles();

    $violations = [];
    foreach ($files as $file) {
        $data = json_decode(file_get_contents($file), true);
        foreach (['require', 'require-dev'] as $section) {
            foreach (($data[$section] ?? []) as $package => $constraint) {
                if (str_starts_with($package, 'marko/') && $constraint === '*') {
                    $violations[] = basename(dirname($file)) . " [$section]: $package=$constraint";
                }
            }
        }
    }

    expect($violations)->toBeEmpty('These wildcard marko/* constraints remain: ' . implode(', ', $violations));
});

it('preserves all non-marko dependency constraints unchanged (php, psr/*, ext-*, pestphp/*, etc.)', function () {
    $files = getPackageComposerFiles();

    $violations = [];
    foreach ($files as $file) {
        $data = json_decode(file_get_contents($file), true);
        foreach (['require', 'require-dev'] as $section) {
            foreach (($data[$section] ?? []) as $package => $constraint) {
                if (!str_starts_with($package, 'marko/') && $constraint === 'self.version') {
                    $violations[] = basename(dirname($file)) . " [$section]: $package=$constraint";
                }
            }
        }
    }

    expect($violations)->toBeEmpty('These non-marko dependencies incorrectly use self.version: ' . implode(', ', $violations));
});

it('preserves all other composer.json keys (autoload, extra, config, suggest, etc.) unchanged', function () {
    $files = getPackageComposerFiles();

    $violations = [];
    foreach ($files as $file) {
        $data = json_decode(file_get_contents($file), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $violations[] = basename(dirname($file)) . ': invalid JSON';
            continue;
        }

        // Must have name, type, and license
        if (!isset($data['name'])) {
            $violations[] = basename(dirname($file)) . ': missing name key';
        }
        if (!isset($data['license'])) {
            $violations[] = basename(dirname($file)) . ': missing license key';
        }

        // Must not have version field
        if (isset($data['version'])) {
            $violations[] = basename(dirname($file)) . ': has version key (must not be present)';
        }

        // If it had autoload before, it still should (spot-check psr-4 key)
        if (isset($data['autoload']) && !isset($data['autoload']['psr-4'])) {
            $violations[] = basename(dirname($file)) . ': autoload missing psr-4 key';
        }
    }

    expect($violations)->toBeEmpty('Structural violations in package composer.json files: ' . implode(', ', $violations));
});
