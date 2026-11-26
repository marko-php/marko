<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Tests\Integration;

use Marko\Database\Exceptions\LostConnectionException;
use Marko\Database\PgSql\Connection\PgSqlConnection;
use Marko\Database\PgSql\Tests\Fixtures\IntegrationDatabase;
use RuntimeException;

/*
 * A session killed by the server (pg_terminate_backend from a second
 * connection) against a real PostgreSQL server. Uses the MARKO_TEST_PGSQL_*
 * variables and skips when they are unset (fails with
 * MARKO_INTEGRATION_REQUIRED). Part of the integration-services group.
 */

/**
 * Terminate the session of $victim from $killer, wait until the backend is
 * gone, and return the pid that was terminated.
 */
function pgsqlTerminateSession(
    PgSqlConnection $victim,
    PgSqlConnection $killer,
): int {
    $pid = (int) $victim->query('SELECT pg_backend_pid() AS pid')[0]['pid'];
    $terminated = $killer->query('SELECT pg_terminate_backend(:pid, 5000) AS terminated', ['pid' => $pid]);

    if ($terminated[0]['terminated'] !== true) {
        throw new RuntimeException("Backend $pid was not terminated within 5 seconds");
    }

    return $pid;
}

pest()->group('integration-services');

beforeEach(function (): void {
    $config = IntegrationDatabase::config();

    if ($config === null) {
        $this->markTestSkipped(
            IntegrationDatabase::SKIP_REASON,
        );
    }

    $this->victim = new PgSqlConnection($config);
    $this->killer = new PgSqlConnection($config);
});

afterEach(function (): void {
    if (isset($this->victim)) {
        $this->victim->disconnect();
        $this->killer->disconnect();
    }
});

describe('PostgreSQL lost connection', function (): void {
    it('reconnects and succeeds after the server terminates the session', function (): void {
        $terminatedPid = pgsqlTerminateSession($this->victim, $this->killer);

        expect($this->victim->query('SELECT pg_backend_pid() AS pid')[0]['pid'])->not->toBe($terminatedPid);

        pgsqlTerminateSession($this->victim, $this->killer);

        expect($this->victim->execute('SELECT 1'))->toBe(1);
    });

    it(
        'throws LostConnectionException for a statement inside a transaction after the server terminates the session and reconnects after the rollback',
        function (): void {
            $this->victim->beginTransaction();
            $this->victim->query('SELECT 1 AS n');

            pgsqlTerminateSession($this->victim, $this->killer);

            expect(fn () => $this->victim->query('SELECT 1 AS n'))->toThrow(LostConnectionException::class)
                ->and($this->victim->transactionLevel())->toBe(1)
                ->and(fn () => $this->victim->query('SELECT 1 AS n'))->toThrow(LostConnectionException::class);

            $this->victim->rollback();

            expect($this->victim->transactionLevel())->toBe(0)
                ->and($this->victim->isConnected())->toBeFalse()
                ->and($this->victim->query('SELECT 1 AS n'))->toBe([['n' => 1]])
                ->and($this->victim->isConnected())->toBeTrue();
        },
    );

    it(
        'throws LostConnectionException from commit after the server terminates the session and reconnects on the next statement',
        function (): void {
            $this->victim->beginTransaction();
            $this->victim->query('SELECT 1 AS n');

            pgsqlTerminateSession($this->victim, $this->killer);

            expect(fn () => $this->victim->commit())->toThrow(LostConnectionException::class)
                ->and($this->victim->transactionLevel())->toBe(0)
                ->and($this->victim->query('SELECT 1 AS n'))->toBe([['n' => 1]]);
        },
    );

    it(
        'reconnects when beginTransaction is the first call on a session the server terminated',
        function (): void {
            pgsqlTerminateSession($this->victim, $this->killer);

            $this->victim->beginTransaction();

            expect($this->victim->transactionLevel())->toBe(1)
                ->and($this->victim->query('SELECT 1 AS n'))->toBe([['n' => 1]]);

            $this->victim->commit();

            expect($this->victim->transactionLevel())->toBe(0);
        },
    );
});
