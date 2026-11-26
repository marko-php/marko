<?php

declare(strict_types=1);

namespace Marko\Database\MySql\Tests\Integration;

use Marko\Database\Config\DatabaseConfig;
use Marko\Database\Exceptions\LostConnectionException;
use Marko\Database\MySql\Connection\MySqlConnection;
use Marko\Database\MySql\Tests\Fixtures\IntegrationDatabase;

/*
 * A session the server ends (here: KILL from a second connection) against a
 * real MySQL or MariaDB server. Settings come from
 * tests/Fixtures/IntegrationDatabase. With MARKO_INTEGRATION_REQUIRED set
 * (CI), a missing host fails instead of skipping. Part of the
 * integration-services group.
 */

/**
 * Ends the session of $victim from $killer and waits until the server has
 * dropped it (KILL returns before the victim's thread is gone).
 */
function mysqlKillSession(
    MySqlConnection $victim,
    MySqlConnection $killer,
): void {
    $id = (int) $victim->query('SELECT CONNECTION_ID() AS id')[0]['id'];
    $killer->execute('KILL ' . $id);

    $deadline = microtime(true) + 10.0;

    while (
        $killer->query('SELECT 1 FROM information_schema.PROCESSLIST WHERE ID = ?', [$id]) !== []
        && microtime(true) < $deadline
    ) {
        usleep(20_000);
    }

    expect($killer->query('SELECT 1 FROM information_schema.PROCESSLIST WHERE ID = ?', [$id]))->toBeEmpty();
}

pest()->group('integration-services');

beforeEach(function (): void {
    $config = IntegrationDatabase::config();

    if ($config === null) {
        $this->markTestSkipped(IntegrationDatabase::SKIP_REASON);
    }

    /** @var DatabaseConfig $config */
    $this->victim = new MySqlConnection($config);
    $this->killer = new MySqlConnection($config);
});

afterEach(function (): void {
    if (isset($this->victim)) {
        $this->victim->disconnect();
        $this->killer->disconnect();
    }
});

describe('MySQL lost connection', function (): void {
    it('reconnects and succeeds after the server kills the session', function (): void {
        expect($this->victim->query('SELECT 1 AS one'))->toBe([['one' => 1]]);

        mysqlKillSession($this->victim, $this->killer);

        expect($this->victim->query('SELECT 2 AS two'))->toBe([['two' => 2]])
            ->and($this->victim->execute('DO 1'))->toBe(0);
    });

    it(
        'throws LostConnectionException for a statement inside a transaction after the server kills the session and reconnects after the rollback',
        function (): void {
            $this->victim->beginTransaction();
            $this->victim->query('SELECT 1 AS one');

            mysqlKillSession($this->victim, $this->killer);

            expect(fn () => $this->victim->query('SELECT 2 AS two'))->toThrow(LostConnectionException::class)
                ->and($this->victim->transactionLevel())->toBe(1);

            $this->victim->rollback();

            expect($this->victim->transactionLevel())->toBe(0)
                ->and($this->victim->isConnected())->toBeFalse()
                ->and($this->victim->query('SELECT 3 AS three'))->toBe([['three' => 3]]);
        },
    );

    it(
        'throws LostConnectionException from commit after the server kills the session and reconnects on the next statement',
        function (): void {
            $this->killer->execute('DROP TABLE IF EXISTS lost_connection_items');
            $this->killer->execute('CREATE TABLE lost_connection_items (name VARCHAR(20)) ENGINE=InnoDB');

            try {
                $this->victim->beginTransaction();
                $this->victim->execute('INSERT INTO lost_connection_items (name) VALUES (?)', ['a']);

                mysqlKillSession($this->victim, $this->killer);

                expect(fn () => $this->victim->commit())->toThrow(LostConnectionException::class)
                    ->and($this->victim->transactionLevel())->toBe(0)
                    ->and($this->victim->query('SELECT COUNT(*) AS total FROM lost_connection_items')[0]['total'])
                    ->toBe(0);
            } finally {
                $this->killer->execute('DROP TABLE IF EXISTS lost_connection_items');
            }
        },
    );
});
