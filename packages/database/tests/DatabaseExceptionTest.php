<?php

declare(strict_types=1);

use Marko\Database\Exceptions\DatabaseException;

describe('DatabaseException', function (): void {
    it('throws DatabaseException with helpful message when no driver is installed', function (): void {
        $exception = DatabaseException::noDriverInstalled('mysql');

        expect($exception)->toBeInstanceOf(DatabaseException::class)
            ->and($exception->getMessage())->toContain('mysql')
            ->and($exception->getMessage())->toContain('driver')
            ->and($exception->getMessage())->toContain('not installed')
            ->and($exception->getContext())->not->toBeEmpty();
    });

    it('includes suggestion to install driver package in exception message', function (): void {
        $mysqlException = DatabaseException::noDriverInstalled('mysql');
        $pgsqlException = DatabaseException::noDriverInstalled('pgsql');

        expect($mysqlException->getSuggestion())->toContain('composer require')
            ->and($mysqlException->getSuggestion())->toContain('marko/database-mysql')
            ->and($pgsqlException->getSuggestion())->toContain('composer require')
            ->and($pgsqlException->getSuggestion())->toContain('marko/database-pgsql');
    });

    it('uses KNOWN_DRIVERS constant instead of DRIVER_PACKAGES', function (): void {
        $reflection = new ReflectionClass(DatabaseException::class);

        $constants = $reflection->getConstants();

        expect($reflection->hasMethod('noDriverInstalled'))->toBeTrue()
            ->and(array_key_exists('KNOWN_DRIVERS', $constants))->toBeTrue()
            ->and(array_key_exists('DRIVER_PACKAGES', $constants))->toBeFalse();
    });
});
