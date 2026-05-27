<?php

declare(strict_types=1);

namespace Marko\Media\Exceptions;

class NoDriverException extends MediaException
{
    public static function noDriverInstalled(): self
    {
        $drivers = require __DIR__ . '/../../known-drivers.php';
        $packageList = self::formatDriverList($drivers);

        return new self(
            message: 'No media driver installed.',
            context: 'Attempted to resolve a media processing interface but no implementation is bound.',
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
