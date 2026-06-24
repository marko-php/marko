<?php

declare(strict_types=1);

namespace Marko\DevAi\Installation;

class DocsDriverResolver
{
    /**
     * Returns the first known driver package whose vendor directory exists,
     * or null if none is installed.
     */
    public function installedDriver(string $projectRoot): ?string
    {
        return array_find(
            array_keys($this->knownDrivers($projectRoot)),
            fn (string $package) => is_dir($projectRoot . '/vendor/' . $package),
        );
    }

    /**
     * Returns a list of known driver packages whose vendor directories are absent.
     *
     * @return list<string>
     */
    public function uninstalledDrivers(string $projectRoot): array
    {
        return array_values(array_filter(
            array_keys($this->knownDrivers($projectRoot)),
            fn (string $package) => !is_dir($projectRoot . '/vendor/' . $package),
        ));
    }

    /**
     * Returns the recommended uninstalled driver (description contains "recommended",
     * case-insensitive), else the first uninstalled, else null.
     */
    public function recommendedUninstalled(string $projectRoot): ?string
    {
        $registry = $this->knownDrivers($projectRoot);
        $uninstalled = $this->uninstalledDrivers($projectRoot);

        $recommended = array_find(
            $uninstalled,
            fn (string $package) => str_contains(
                strtolower($registry[$package] ?? ''),
                'recommended',
            ),
        );

        return $recommended ?? ($uninstalled[0] ?? null);
    }

    /**
     * Derives the build command from the package name by stripping the vendor
     * prefix and appending ":build" (e.g. "marko/docs-fts" → "docs-fts:build").
     */
    public function buildCommand(string $package): string
    {
        $name = substr($package, (int) strpos($package, '/') + 1);

        return $name . ':build';
    }

    /**
     * Reads the known-drivers registry from vendor/marko/docs/known-drivers.php.
     * Returns an empty array if the file is absent.
     *
     * @return array<string, string>
     */
    private function knownDrivers(string $projectRoot): array
    {
        $file = $projectRoot . '/vendor/marko/docs/known-drivers.php';

        if (!is_file($file)) {
            return [];
        }

        /** @var array<string, string> $drivers */
        $drivers = require $file;

        return $drivers;
    }
}
