<?php

declare(strict_types=1);

namespace Marko\Cache\Exceptions;

use Marko\Cache\Contracts\LockStoreInterface;
use Marko\Core\Exceptions\MarkoException;

class NoDriverException extends MarkoException
{
    /**
     * @param string|null $interface The unresolved interface, passed by the container
     */
    public static function noDriverInstalled(
        ?string $interface = null,
    ): self {
        $drivers = require __DIR__ . '/../../known-drivers.php';
        $packageList = self::formatDriverList($drivers);

        if ($interface === LockStoreInterface::class) {
            return new self(
                message: 'No cache lock store installed.',
                context: 'Attempted to resolve LockStoreInterface (needed by LockFactoryInterface) but no implementation is bound.',
                suggestion: "Each cache driver ships a lock store. Install one of these drivers:\n$packageList\n"
                    . 'Only marko/cache-redis excludes across hosts. Or bind your own LockStoreInterface implementation with a Preference.',
            );
        }

        return new self(
            message: 'No cache driver installed.',
            context: 'Attempted to resolve a cache interface but no implementation is bound.',
            suggestion: "Install one of these drivers:\n$packageList",
        );
    }

    /**
     * @param array<string, string> $drivers
     */
    private static function formatDriverList(array $drivers): string
    {
        $lines = [];
        foreach ($drivers as $package => $description) {
            $docsUrl = self::docsUrl($package);
            $lines[] = "- $package: $description";
            $lines[] = "  Install: composer require $package";
            $lines[] = "  Docs: $docsUrl";
        }

        return implode("\n", $lines);
    }

    private static function docsUrl(string $package): string
    {
        $basename = substr($package, strlen('marko/'));

        return "https://marko.build/docs/packages/$basename/";
    }
}
