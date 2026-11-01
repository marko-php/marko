<?php

declare(strict_types=1);

namespace Marko\View\Exceptions;

use Marko\View\TemplatePrecompilerInterface;

class NoDriverException extends ViewException
{
    /**
     * @param string|null $interface The view interface that has no binding, when the container knows it
     */
    public static function noDriverInstalled(
        ?string $interface = null,
    ): self {
        $drivers = require __DIR__ . '/../../known-drivers.php';
        $packageList = self::formatDriverList($drivers);

        if ($interface === TemplatePrecompilerInterface::class) {
            return new self(
                message: 'No template precompiler is bound.',
                context: "Attempted to resolve $interface but no implementation is bound. "
                    . 'view:cache needs the installed view driver to compile templates ahead of time.',
                suggestion: "Install or update a view driver that provides a template precompiler:\n$packageList",
            );
        }

        return new self(
            message: 'No view driver installed.',
            context: 'Attempted to resolve ViewInterface but no implementation is bound.',
            suggestion: "Install a view driver:\n$packageList",
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
