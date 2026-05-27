<?php

declare(strict_types=1);

use Marko\Core\Exceptions\MarkoException;
use Marko\Inertia\Exceptions\NoDriverException;

describe('NoDriverException', function (): void {
    it('inertia NoDriverException reads from known-drivers.php and includes docs URLs', function (): void {
        $knownDrivers = require __DIR__ . '/../../known-drivers.php';
        $exception = NoDriverException::noDriverInstalled();

        foreach (array_keys($knownDrivers) as $package) {
            $basename = substr($package, strlen('marko/'));
            expect($exception->getSuggestion())
                ->toContain($package)
                ->and($exception->getSuggestion())
                ->toContain("https://marko.build/docs/packages/$basename/");
        }
    });

    it('includes the description for each driver in the suggestion', function (): void {
        $knownDrivers = require __DIR__ . '/../../known-drivers.php';
        $exception = NoDriverException::noDriverInstalled();

        foreach ($knownDrivers as $package => $description) {
            expect($exception->getSuggestion())->toContain($description);
        }
    });

    it('includes a composer require command for each driver', function (): void {
        $knownDrivers = require __DIR__ . '/../../known-drivers.php';
        $exception = NoDriverException::noDriverInstalled();

        foreach (array_keys($knownDrivers) as $package) {
            expect($exception->getSuggestion())->toContain("composer require $package");
        }
    });

    it('extends MarkoException', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception)->toBeInstanceOf(MarkoException::class);
    });
});
