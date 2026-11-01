<?php

declare(strict_types=1);

use Marko\Core\Container\Container;
use Marko\Core\Exceptions\MarkoException;
use Marko\Validation\Contracts\FlashedFormStateInterface;
use Marko\Validation\Contracts\RecordLookupInterface;
use Marko\Validation\Contracts\RuleInterface;
use Marko\Validation\Exceptions\NoDriverException;
use Marko\Validation\Rules\RecordRules;

describe('NoDriverException', function (): void {
    it('tells you to install marko/validation-database when no record lookup driver is bound', function (): void {
        $exception = NoDriverException::noDriverInstalled(RecordLookupInterface::class);

        expect($exception)->toBeInstanceOf(MarkoException::class)
            ->and($exception->getMessage())->toBe('No record lookup driver installed.')
            ->and($exception->getContext())->toContain(RecordLookupInterface::class)
            ->and($exception->getSuggestion())->toContain('composer require marko/validation-database')
            ->and($exception->getSuggestion())->toContain('https://marko.build/docs/packages/validation-database/');
    });

    it('reports a missing binding, not a missing driver, for any other validation interface', function (): void {
        $exception = NoDriverException::noDriverInstalled(RuleInterface::class);

        expect($exception->getMessage())->toBe('No implementation is bound for ' . RuleInterface::class . '.')
            ->and($exception->getSuggestion())->not->toContain('marko/validation-database');
    });

    it('tells you to install marko/forms when no flashed form state reader is bound', function (): void {
        $exception = NoDriverException::noDriverInstalled(FlashedFormStateInterface::class);

        expect($exception->getMessage())->toBe('No flashed form state reader installed.')
            ->and($exception->getContext())->toContain(FlashedFormStateInterface::class)
            ->and($exception->getSuggestion())->toContain('composer require marko/forms')
            ->and($exception->getSuggestion())->toContain('https://marko.build/docs/packages/forms/');
    });

    it('is thrown by the container when FlashedFormStateInterface is resolved without marko/forms', function (): void {
        expect(fn () => new Container()->get(FlashedFormStateInterface::class))
            ->toThrow(NoDriverException::class, 'No flashed form state reader installed.');
    });

    it('is thrown by the container when RecordRules is resolved without a driver', function (): void {
        $container = new Container();

        expect(fn () => $container->get(RecordRules::class))
            ->toThrow(NoDriverException::class, 'No record lookup driver installed.');
    });

    it('lists marko/validation-database in known-drivers.php', function (): void {
        $drivers = require dirname(__DIR__, 3) . '/known-drivers.php';

        expect(array_keys($drivers))->toBe(['marko/validation-database']);
    });
});
