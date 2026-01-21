<?php

declare(strict_types=1);

use Marko\Database\Exceptions\DatabaseException;

describe('DatabaseException', function (): void {
    it('throws DatabaseException with helpful message when no driver is installed', function (): void {
        $exception = DatabaseException::noDriverInstalled('mysql');

        expect($exception)->toBeInstanceOf(DatabaseException::class);
        expect($exception->getMessage())->toContain('mysql');
        expect($exception->getMessage())->toContain('driver');
        expect($exception->getMessage())->toContain('not installed');
        expect($exception->getContext())->not->toBeEmpty();
    });

    it('includes suggestion to install driver package in exception message', function (): void {
        $mysqlException = DatabaseException::noDriverInstalled('mysql');
        $pgsqlException = DatabaseException::noDriverInstalled('pgsql');

        expect($mysqlException->getSuggestion())->toContain('composer require');
        expect($mysqlException->getSuggestion())->toContain('marko/database-mysql');

        expect($pgsqlException->getSuggestion())->toContain('composer require');
        expect($pgsqlException->getSuggestion())->toContain('marko/database-pgsql');
    });
});
