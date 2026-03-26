<?php

declare(strict_types=1);

use Marko\Core\Exceptions\MarkoException;
use Marko\Database\Exceptions\NoDriverException;

describe('NoDriverException', function (): void {
    it('has DRIVER_PACKAGES constant listing marko/database-mysql and marko/database-pgsql', function (): void {
        $reflection = new ReflectionClass(NoDriverException::class);
        $constant = $reflection->getReflectionConstant('DRIVER_PACKAGES');

        expect($constant)->not->toBeFalse()
            ->and($constant->getValue())->toContain('marko/database-mysql')
            ->and($constant->getValue())->toContain('marko/database-pgsql');
    });

    it('provides suggestion with composer require commands for all driver packages', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception->getSuggestion())->toContain('composer require marko/database-mysql')
            ->and($exception->getSuggestion())->toContain('composer require marko/database-pgsql');
    });

    it('includes context about resolving database interfaces', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception->getContext())->toContain('database interface');
    });

    it('extends MarkoException', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception)->toBeInstanceOf(MarkoException::class);
    });
});
