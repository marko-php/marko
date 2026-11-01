<?php

declare(strict_types=1);

namespace Marko\Validation\Database\Tests\Integration\MySql;

use Marko\Database\MySql\Connection\MySqlConnection;
use Marko\Database\MySql\Sql\MySqlGenerator;
use Marko\Database\MySql\Tests\Fixtures\IntegrationDatabase;
use Marko\Validation\Database\Tests\Fixtures\LookupTables;
use Marko\Validation\Database\Tests\Fixtures\RecordLookupCases;

/*
 * The Exists and Unique rules through EntityRecordLookup against a real MySQL server; CI also runs this directory
 * against MariaDB. The tables are built from the fixture entities through MySqlGenerator, as db:migrate builds
 * them. Settings come from the database-mysql IntegrationDatabase fixture (MARKO_TEST_MYSQL_*); the tests skip
 * without MARKO_TEST_MYSQL_HOST and fail instead with MARKO_INTEGRATION_REQUIRED set. The tests create and drop
 * their tables. See RecordLookupCases for the cases.
 */

pest()->group('integration-services');

beforeEach(function (): void {
    $config = IntegrationDatabase::config();

    if ($config === null) {
        $this->markTestSkipped(IntegrationDatabase::SKIP_REASON);
    }

    $this->connection = new MySqlConnection($config);
    LookupTables::drop($this->connection);
    LookupTables::create($this->connection, new MySqlGenerator());
    $this->rules = LookupTables::recordRules($this->connection);
});

afterEach(function (): void {
    if (isset($this->connection)) {
        LookupTables::drop($this->connection);
        $this->connection->disconnect();
    }
});

describe('Exists and Unique on MySQL', function (): void {
    RecordLookupCases::register(caseSensitiveByDefault: false);
});
