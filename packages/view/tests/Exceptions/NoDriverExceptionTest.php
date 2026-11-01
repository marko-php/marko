<?php

declare(strict_types=1);

use Marko\View\Exceptions\NoDriverException;
use Marko\View\Exceptions\ViewException;
use Marko\View\TemplatePrecompilerInterface;
use Marko\View\ViewInterface;

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

    it('derives docs URLs from the package basename (marko slash prefix stripped)', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception->getSuggestion())->toContain('https://marko.build/docs/packages/view-twig/')
            ->and($exception->getSuggestion())->toContain('https://marko.build/docs/packages/view-latte/');
    });

    it('lists view-twig first in the suggestion (matching known-drivers.php order)', function (): void {
        $exception = NoDriverException::noDriverInstalled();
        $suggestion = $exception->getSuggestion();

        $twigPos = strpos($suggestion, 'marko/view-twig');
        $lattePos = strpos($suggestion, 'marko/view-latte');

        expect($twigPos)->toBeLessThan($lattePos);
    });

    it('no longer exposes a DRIVER_PACKAGES const', function (): void {
        $reflection = new ReflectionClass(NoDriverException::class);
        $constant = $reflection->getReflectionConstant('DRIVER_PACKAGES');

        expect($constant)->toBeFalse();
    });

    it('provides suggestion with composer require commands for all driver packages', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception->getSuggestion())->toContain('composer require marko/view-twig')
            ->and($exception->getSuggestion())->toContain('composer require marko/view-latte');
    });

    it('includes context about resolving ViewInterface', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception->getContext())->toContain(
            'Attempted to resolve ViewInterface but no implementation is bound.',
        );
    });

    it('extends ViewException', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception)->toBeInstanceOf(ViewException::class);
    });

    it('explains a missing template precompiler instead of a missing driver', function (): void {
        $exception = NoDriverException::noDriverInstalled(TemplatePrecompilerInterface::class);

        expect($exception->getMessage())->toBe('No template precompiler is bound.')
            ->and($exception->getContext())->toContain(TemplatePrecompilerInterface::class)
            ->and($exception->getSuggestion())->toContain('marko/view-latte')
            ->and($exception->getSuggestion())->toContain('marko/view-twig');
    });

    it('keeps the driver message for other view interfaces', function (): void {
        $exception = NoDriverException::noDriverInstalled(ViewInterface::class);

        expect($exception->getMessage())->toBe('No view driver installed.');
    });
});
