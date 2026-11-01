<?php

declare(strict_types=1);

namespace Marko\Validation\Database\Tests\Integration\PgSql;

use Marko\Database\PgSql\Connection\PgSqlConnection;
use Marko\Database\PgSql\Sql\PgSqlGenerator;
use Marko\Database\PgSql\Tests\Fixtures\IntegrationDatabase;
use Marko\Validation\Database\Tests\Fixtures\LookupTables;
use Marko\Validation\Database\Tests\Fixtures\RecordLookupCases;

/*
 * The Exists and Unique rules through EntityRecordLookup against a real PostgreSQL server. The tables are built
 * from the fixture entities through PgSqlGenerator, as db:migrate builds them. Settings come from the
 * database-pgsql IntegrationDatabase fixture (MARKO_TEST_PGSQL_*); the tests skip without MARKO_TEST_PGSQL_HOST
 * and fail instead with MARKO_INTEGRATION_REQUIRED set. The tests create and drop their tables. See
 * RecordLookupCases for the cases.
 */

pest()->group('integration-services');

beforeEach(function (): void {
    $config = IntegrationDatabase::config();

    if ($config === null) {
        $this->markTestSkipped(IntegrationDatabase::SKIP_REASON);
    }

    $this->connection = new PgSqlConnection($config);
    LookupTables::drop($this->connection);
    LookupTables::create($this->connection, new PgSqlGenerator());
    $this->rules = LookupTables::recordRules($this->connection);
});

afterEach(function (): void {
    if (isset($this->connection)) {
        LookupTables::drop($this->connection);
        $this->connection->disconnect();
    }
});

describe('Exists and Unique on PostgreSQL', function (): void {
    RecordLookupCases::register(caseSensitiveByDefault: true);
});
