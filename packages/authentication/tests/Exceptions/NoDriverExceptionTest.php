<?php

declare(strict_types=1);

use Marko\Authentication\Exceptions\NoDriverException;
use Marko\Core\Exceptions\MarkoException;

describe('NoDriverException', function (): void {
    it('authentication NoDriverException reads from known-drivers.php and includes docs URL', function (): void {
        $knownDrivers = require __DIR__ . '/../../known-drivers.php';
        $exception = NoDriverException::noDriverInstalled();

        foreach ($knownDrivers as $package => $description) {
            $basename = substr($package, strlen('marko/'));
            expect($exception->getSuggestion())
                ->toContain($package)
                ->and($exception->getSuggestion())->toContain($description)
                ->and($exception->getSuggestion())->toContain("composer require $package")
                ->and($exception->getSuggestion())->toContain("https://marko.build/docs/packages/$basename/");
        }
    });

    it('no longer exposes a DRIVER_PACKAGES const', function (): void {
        $reflection = new ReflectionClass(NoDriverException::class);
        $constant = $reflection->getReflectionConstant('DRIVER_PACKAGES');

        expect($constant)->toBeFalse();
    });

    it('includes context about resolving authentication interfaces', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception->getContext())->toContain('authentication interface');
    });

    it('extends MarkoException', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception)->toBeInstanceOf(MarkoException::class);
    });
});
