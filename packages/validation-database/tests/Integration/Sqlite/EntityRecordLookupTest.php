<?php

declare(strict_types=1);

namespace Marko\Validation\Database\Tests\Integration\Sqlite;

use Marko\Database\Config\DatabaseTimezoneConfig;
use Marko\Database\Sqlite\Sql\SqliteGenerator;
use Marko\Database\Sqlite\Tests\Fixtures\SqliteTestDatabase;
use Marko\Validation\Database\Tests\Fixtures\LookupAccount;
use Marko\Validation\Database\Tests\Fixtures\LookupChannel;
use Marko\Validation\Database\Tests\Fixtures\LookupTables;
use Marko\Validation\Database\Tests\Fixtures\RecordLookupCases;

/*
 * The Exists and Unique rules through EntityRecordLookup against a real SQLite connection. The tables are built
 * from the fixture entities through SqliteGenerator, as db:migrate builds them. SQLite compares text with the
 * BINARY collation by default, so unique() is case-sensitive unless caseInsensitive() is used; LOWER() only folds
 * ASCII letters. Runs in the plain suite on a private temp file and on an in-memory database. See
 * RecordLookupCases for the cases.
 */

function sqliteLookupTables(
    object $test,
    string $kind,
): void {
    $test->database = new SqliteTestDatabase($kind);
    LookupTables::create($test->database->connection, new SqliteGenerator(DatabaseTimezoneConfig::fromName('UTC')));
    $test->rules = LookupTables::recordRules($test->database->connection);
}

afterEach(function (): void {
    $this->database->dispose();
});

describe('Exists and Unique on SQLite', function (): void {
    beforeEach(function (): void {
        sqliteLookupTables($this, SqliteTestDatabase::MEMORY);
    });

    RecordLookupCases::register(caseSensitiveByDefault: true);
});

describe('Exists and Unique on a SQLite file', function (): void {
    beforeEach(function (): void {
        sqliteLookupTables($this, SqliteTestDatabase::FILE);
    });

    RecordLookupCases::register(caseSensitiveByDefault: true);
});

describe('Rules against SQLite tables', function (): void {
    beforeEach(function (): void {
        sqliteLookupTables($this, SqliteTestDatabase::MEMORY);
    });

    it('validates unique and exists rules against sqlite tables', function (): void {
        $unique = $this->rules->unique(LookupAccount::class, 'email');

        expect($this->rules->exists(LookupChannel::class)->passes('channel_id', 1, []))->toBeTrue()
            ->and($this->rules->exists(LookupChannel::class)->passes('channel_id', 99, []))->toBeFalse()
            ->and($unique->passes('email', 'grace@example.com', []))->toBeFalse()
            ->and($unique->passes('email', 'GRACE@example.com', []))->toBeTrue()
            ->and($unique->caseInsensitive()->passes('email', 'GRACE@example.com', []))->toBeFalse();
    });

    it('folds only ascii letters in caseInsensitive() because sqlite LOWER() is ascii-only', function (): void {
        $this->database->connection->execute(
            'INSERT INTO lookup_accounts (id, email_address, tenant_ref) VALUES (?, ?, ?)',
            [3, 'élan@example.com', 1],
        );
        $unique = $this->rules->unique(LookupAccount::class, 'email')->caseInsensitive();

        expect($unique->passes('email', 'ÉLAN@example.com', []))->toBeTrue()
            ->and($unique->passes('email', 'élan@EXAMPLE.com', []))->toBeFalse();
    });
});
