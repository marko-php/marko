<?php

declare(strict_types=1);

namespace Marko\Core\Exceptions;

use Psr\Container\ContainerExceptionInterface;

class BindingException extends MarkoException implements ContainerExceptionInterface
{
    public static function noImplementation(
        string $interface,
    ): self {
        // Try to suggest available driver packages based on interface namespace
        $driverPackages = self::discoverDriverPackages($interface);

        if ($driverPackages !== []) {
            $packageList = array_map(
                fn ($pkg) => "- `composer require $pkg:\"dev-develop as 0.1.0\"`",
                $driverPackages,
            );
            $suggestion = "Option 1: Install an available driver package:\n" . implode("\n", $packageList);
            $suggestion .= "\n\nOption 2: Register a binding in module.php:\n`'bindings' => ['$interface' => YourImplementation::class]`";
        } else {
            $suggestion = "Register a binding in module.php:\n`'bindings' => ['$interface' => YourImplementation::class]`";
        }

        return new self(
            message: "No implementation bound for interface '$interface'",
            context: "Attempted to resolve `$interface`, but no binding found",
            suggestion: $suggestion,
        );
    }

    /**
     * Discover available driver packages for an interface.
     *
     * Extracts the package prefix from the interface namespace (e.g., Marko\Cache\... → cache)
     * and scans for matching marko/{prefix}-* packages in the vendor directory.
     *
     * @return array<string> List of available package names (e.g., ['marko/cache-array', 'marko/cache-file'])
     */
    private static function discoverDriverPackages(
        string $interface,
    ): array {
        // Extract namespace prefix (e.g., "Marko\Cache\Contracts\CacheInterface" → "Cache")
        if (!str_starts_with($interface, 'Marko\\')) {
            return [];
        }

        $parts = explode('\\', $interface);
        if (count($parts) < 2) {
            return [];
        }

        // Get the package name (second part of namespace, lowercase)
        $packagePrefix = strtolower($parts[1]);

        // This file is at: vendor/marko/core/src/Exceptions/BindingException.php (standard install)
        // Or at: packages/core/src/Exceptions/BindingException.php (monorepo)
        $corePackagePath = dirname(__DIR__, 2); // packages/core or vendor/marko/core
        $parentDir = dirname($corePackagePath);  // packages or vendor/marko

        $packages = [];

        // Check if we're in a standard vendor installation (parent is "marko" dir inside vendor)
        if (basename($parentDir) === 'marko') {
            // Standard install: scan vendor/marko/{prefix}-*
            $packages = self::scanForDriverPackages($parentDir, $packagePrefix);
        } else {
            // Monorepo: scan packages/{prefix}-*
            $packages = self::scanForDriverPackages($parentDir, $packagePrefix);
        }

        return array_unique($packages);
    }

    /**
     * Scan a directory for driver packages matching a prefix.
     *
     * @return array<string>
     */
    private static function scanForDriverPackages(
        string $basePath,
        string $packagePrefix,
    ): array {
        $packages = [];
        $pattern = $basePath . '/' . $packagePrefix . '-*';
        $dirs = glob($pattern, GLOB_ONLYDIR);

        if ($dirs === false) {
            return [];
        }

        foreach ($dirs as $dir) {
            $composerJson = $dir . '/composer.json';
            if (file_exists($composerJson)) {
                $content = file_get_contents($composerJson);
                if ($content !== false) {
                    $data = json_decode($content, true);
                    if (isset($data['name'])) {
                        $packages[] = $data['name'];
                    }
                }
            }
        }

        return $packages;
    }

    public static function unresolvableParameter(
        string $parameter,
        string $class,
    ): self {
        return new self(
            message: "Cannot resolve parameter '\$$parameter' in class '$class'",
            context: 'Parameter has no type declaration or is a built-in type that cannot be autowired',
            suggestion: "Add a class or interface type hint, or register a factory for '$class'",
        );
    }

    public static function unresolvableCallableParameter(
        string $parameter,
    ): self {
        return new self(
            message: "Cannot resolve parameter '\$$parameter' in callable",
            context: 'Parameter has no type declaration or is a built-in type that cannot be autowired',
            suggestion: "Add a class or interface type hint to the parameter",
        );
    }
}
