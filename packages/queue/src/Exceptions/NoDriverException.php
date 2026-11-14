<?php

declare(strict_types=1);

namespace Marko\Queue\Exceptions;

use Marko\Queue\FailedJobRepositoryInterface;
use Marko\Queue\QueueInterface;

class NoDriverException extends QueueException
{
    private const array DRIVER_CONTRACTS = [
        QueueInterface::class,
        FailedJobRepositoryInterface::class,
    ];

    /**
     * @param string|null $interface The interface the container failed to resolve. Only the
     *                               driver contracts (QueueInterface, FailedJobRepositoryInterface)
     *                               mean a driver is missing; any other queue interface simply
     *                               has no binding, and the message says so.
     */
    public static function noDriverInstalled(
        ?string $interface = null,
    ): self {
        if (
            $interface !== null
            && !in_array($interface, self::DRIVER_CONTRACTS, true)
        ) {
            return new self(
                message: "No implementation is bound for $interface.",
                context: "Attempted to resolve $interface, but no module binds it to a concrete class.",
                suggestion: "Bind $interface to an implementation in a module.php 'bindings' array, "
                    . "e.g. $interface::class => YourImplementation::class",
            );
        }

        $drivers = require __DIR__ . '/../../known-drivers.php';
        $packageList = self::formatDriverList($drivers);

        return new self(
            message: 'No queue driver installed.',
            context: 'Attempted to resolve a queue interface but no implementation is bound.',
            suggestion: "Install one of these drivers:\n$packageList",
        );
    }

    /**
     * @param array<string, string> $drivers
     */
    private static function formatDriverList(
        array $drivers,
    ): string {
        $lines = [];
        foreach ($drivers as $package => $description) {
            $docsUrl = self::docsUrl($package);
            $lines[] = "- $package: $description";
            $lines[] = "  Install: composer require $package";
            $lines[] = "  Docs: $docsUrl";
        }

        return implode("\n", $lines);
    }

    private static function docsUrl(
        string $package,
    ): string {
        $basename = substr($package, strlen('marko/'));

        return "https://marko.build/docs/packages/$basename/";
    }
}
