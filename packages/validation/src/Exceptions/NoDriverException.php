<?php

declare(strict_types=1);

namespace Marko\Validation\Exceptions;

use Marko\Core\Exceptions\MarkoException;
use Marko\Validation\Contracts\FlashedFormStateInterface;
use Marko\Validation\Contracts\RecordLookupInterface;

/**
 * Thrown by the container when a validation interface has no binding: for RecordLookupInterface (the Exists and
 * Unique rules) it names the driver packages to install, and for FlashedFormStateInterface it names marko/forms.
 */
class NoDriverException extends MarkoException
{
    private const array DRIVER_CONTRACTS = [
        RecordLookupInterface::class,
    ];

    /**
     * @param string|null $interface The interface the container failed to resolve. Only a driver contract
     *                               (RecordLookupInterface) means a driver is missing; any other validation
     *                               interface simply has no binding, and the message says so.
     */
    public static function noDriverInstalled(
        ?string $interface = null,
    ): self {
        if ($interface === FlashedFormStateInterface::class) {
            return new self(
                message: 'No flashed form state reader installed.',
                context: "Attempted to resolve $interface, which reads the validation errors and old input a failed "
                    . 'form submission flashed, but no implementation is bound.',
                suggestion: "Install marko/forms, which provides it:\n"
                    . "  Install: composer require marko/forms\n"
                    . '  Docs: https://marko.build/docs/packages/forms/',
            );
        }

        if ($interface !== null && !in_array($interface, self::DRIVER_CONTRACTS, true)) {
            return new self(
                message: "No implementation is bound for $interface.",
                context: "Attempted to resolve $interface, but no module binds it to a concrete class.",
                suggestion: "Bind $interface to an implementation in a module.php 'bindings' array, "
                    . "e.g. $interface::class => YourImplementation::class",
            );
        }

        $drivers = require __DIR__ . '/../../known-drivers.php';
        $contract = $interface ?? RecordLookupInterface::class;

        return new self(
            message: 'No record lookup driver installed.',
            context: "Attempted to resolve $contract, which the Exists and Unique rules (RecordRules) need "
                . 'to look up stored records, but no implementation is bound.',
            suggestion: "Install one of these drivers:\n" . self::formatDriverList($drivers),
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
            $basename = substr($package, strlen('marko/'));
            $lines[] = "- $package: $description";
            $lines[] = "  Install: composer require $package";
            $lines[] = "  Docs: https://marko.build/docs/packages/$basename/";
        }

        return implode("\n", $lines);
    }
}
