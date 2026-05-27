<?php

declare(strict_types=1);

use Marko\Core\Exceptions\MarkoException;
use Marko\Filesystem\Exceptions\NoDriverException;

describe('NoDriverException', function (): void {
    it('filesystem NoDriverException reads from known-drivers.php and includes docs URLs', function (): void {
        $knownDrivers = require __DIR__ . '/../../../known-drivers.php';
        $exception = NoDriverException::noDriverInstalled();

        foreach (array_keys($knownDrivers) as $package) {
            $basename = substr($package, strlen('marko/'));
            expect($exception->getSuggestion())->toContain($package)
                ->and($exception->getSuggestion())->toContain("https://marko.build/docs/packages/$basename/");
        }
    });

    it('loads the driver list from known-drivers.php', function (): void {
        $knownDrivers = require __DIR__ . '/../../../known-drivers.php';
        $exception = NoDriverException::noDriverInstalled();

        foreach (array_keys($knownDrivers) as $package) {
            expect($exception->getSuggestion())->toContain($package);
        }
    });

    it('includes the description for each driver in the suggestion', function (): void {
        $knownDrivers = require __DIR__ . '/../../../known-drivers.php';
        $exception = NoDriverException::noDriverInstalled();

        foreach ($knownDrivers as $package => $description) {
            expect($exception->getSuggestion())->toContain($description);
        }
    });

    it('includes a composer require command for each driver', function (): void {
        $knownDrivers = require __DIR__ . '/../../../known-drivers.php';
        $exception = NoDriverException::noDriverInstalled();

        foreach (array_keys($knownDrivers) as $package) {
            expect($exception->getSuggestion())->toContain("composer require $package");
        }
    });

    it('includes a derived docs URL for each driver', function (): void {
        $knownDrivers = require __DIR__ . '/../../../known-drivers.php';
        $exception = NoDriverException::noDriverInstalled();

        foreach (array_keys($knownDrivers) as $package) {
            $basename = substr($package, strlen('marko/'));
            expect($exception->getSuggestion())->toContain("https://marko.build/docs/packages/$basename/");
        }
    });

    it('derives docs URLs from the package basename (marko slash prefix stripped)', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception->getSuggestion())->toContain('https://marko.build/docs/packages/filesystem-local/')
            ->and($exception->getSuggestion())->toContain('https://marko.build/docs/packages/filesystem-s3/');
    });

    it('lists filesystem-local first in the suggestion (matching known-drivers.php order)', function (): void {
        $exception = NoDriverException::noDriverInstalled();
        $suggestion = $exception->getSuggestion();

        $localPos = strpos($suggestion, 'marko/filesystem-local');
        $s3Pos = strpos($suggestion, 'marko/filesystem-s3');

        expect($localPos)->toBeLessThan($s3Pos);
    });

    it('no longer exposes a DRIVER_PACKAGES const', function (): void {
        $reflection = new ReflectionClass(NoDriverException::class);
        $constant = $reflection->getReflectionConstant('DRIVER_PACKAGES');

        expect($constant)->toBeFalse();
    });

    it('provides suggestion with composer require commands for all driver packages', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception->getSuggestion())->toContain('composer require marko/filesystem-local')
            ->and($exception->getSuggestion())->toContain('composer require marko/filesystem-s3');
    });

    it('includes context about resolving filesystem interfaces', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception->getContext())->toContain('filesystem interface');
    });

    it('extends MarkoException', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception)->toBeInstanceOf(MarkoException::class);
    });
});
