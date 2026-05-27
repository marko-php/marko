<?php

declare(strict_types=1);

use Marko\Translation\Exceptions\NoDriverException;
use Marko\Translation\Exceptions\TranslationException;

describe('NoDriverException', function (): void {
    it('loads the driver list from known-drivers.php', function (): void {
        $knownDrivers = require __DIR__ . '/../../known-drivers.php';
        $exception = NoDriverException::noDriverInstalled();

        foreach (array_keys($knownDrivers) as $package) {
            expect($exception->getSuggestion())->toContain($package);
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

    it('includes a derived docs URL for each driver', function (): void {
        $knownDrivers = require __DIR__ . '/../../known-drivers.php';
        $exception = NoDriverException::noDriverInstalled();

        foreach (array_keys($knownDrivers) as $package) {
            $basename = substr($package, strlen('marko/'));
            expect($exception->getSuggestion())->toContain("https://marko.build/docs/packages/$basename/");
        }
    });

    it('translation NoDriverException reads from known-drivers.php and includes docs URL', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception->getSuggestion())->toContain('marko/translation-file')
            ->and($exception->getSuggestion())->toContain('https://marko.build/docs/packages/translation-file/');
    });

    it('no longer exposes a DRIVER_PACKAGES const', function (): void {
        $reflection = new ReflectionClass(NoDriverException::class);
        $constant = $reflection->getReflectionConstant('DRIVER_PACKAGES');

        expect($constant)->toBeFalse();
    });

    it('provides suggestion with composer require command for translation-file', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception->getSuggestion())->toContain('composer require marko/translation-file');
    });

    it('includes context about resolving translation interfaces', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception->getContext())->toContain('Attempted to resolve a translation interface but no implementation is bound.');
    });

    it('extends TranslationException', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception)->toBeInstanceOf(TranslationException::class);
    });
});
