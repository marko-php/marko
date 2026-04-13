<?php

declare(strict_types=1);

function markoPackagesRoot(): string
{
    return dirname(__DIR__, 2).'/packages';
}

/**
 * @return array<string>
 */
function markoPackageDirectories(): array
{
    $packagesRoot = markoPackagesRoot();
    $packages = array_values(array_filter(
        scandir($packagesRoot),
        fn (string $entry): bool => $entry !== '.'
            && $entry !== '..'
            && is_dir($packagesRoot.'/'.$entry),
    ));

    sort($packages);

    return $packages;
}

function markoExpectedPackageDirectoryCount(): int
{
    return count(markoPackageDirectories());
}

/**
 * @return array<string, array<string, mixed>>
 */
function markoPackageComposerManifests(): array
{
    $packagesRoot = markoPackagesRoot();
    $manifests = [];

    foreach (markoPackageDirectories() as $package) {
        $composerPath = $packagesRoot.'/'.$package.'/composer.json';

        if (! is_file($composerPath)) {
            continue;
        }

        $manifests[$package] = json_decode(
            file_get_contents($composerPath),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }

    ksort($manifests);

    return $manifests;
}

/**
 * @return array<string>
 */
function markoSplitPackageNames(): array
{
    $packages = [];

    foreach (markoPackageComposerManifests() as $manifest) {
        if (($manifest['type'] ?? null) === 'project') {
            continue;
        }

        $name = $manifest['name'] ?? null;
        if (is_string($name) && $name !== '') {
            $packages[] = $name;
        }
    }

    sort($packages);

    return $packages;
}

function markoExpectedSplitPackageCount(): int
{
    return count(markoSplitPackageNames());
}
