<?php

declare(strict_types=1);

namespace Marko\Session\Database\Tests\Unit;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\PrimaryReadInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Session\Config\SessionConfig;
use Marko\Session\Contracts\SessionHandlerInterface;
use Marko\Session\Database\Handler\DatabaseSessionHandler;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;
use RuntimeException;

class MockConnection implements ConnectionInterface
{
    /** @var array<string, array{id: string, payload: string, last_activity: int}> */
    public array $sessions = [];

    /** @var array<int, array{sql: string, bindings: array<int, mixed>}> */
    public array $executedStatements = [];

    /** @var list<string> */
    public array $queriedStatements = [];

    public function __construct(
        private readonly string $driver = 'sqlite',
    ) {}

    public function connect(): void {}

    public function disconnect(): void {}

    public function isConnected(): bool
    {
        return true;
    }

    public function query(
        string $sql,
        array $bindings = [],
    ): array {
        $this->queriedStatements[] = $sql;

        if (
            str_contains($sql, 'SELECT 1')
            && str_contains($sql, 'last_activity >= ?')
        ) {
            [$id, $activeSince] = $bindings;

            if (
                isset($this->sessions[$id])
                && $this->sessions[$id]['last_activity'] >= $activeSince
            ) {
                return [['1' => 1]];
            }

            return [];
        }

        if (
            str_contains($sql, 'SELECT')
            && str_contains($sql, 'WHERE id')
        ) {
            $id = $bindings[0];

            if (isset($this->sessions[$id])) {
                return [['payload' => $this->sessions[$id]['payload']]];
            }

            return [];
        }

        return [];
    }

    public function execute(
        string $sql,
        array $bindings = [],
    ): int {
        $this->executedStatements[] = ['sql' => $sql, 'bindings' => $bindings];

        if (
            str_contains($sql, 'INSERT')
            || str_contains($sql, 'insert')
        ) {
            $id = $bindings[0];
            $payload = $bindings[1];
            $lastActivity = $bindings[2];

            if (isset($this->sessions[$id])) {
                // Upsert: update existing
                $this->sessions[$id]['payload'] = $payload;
                $this->sessions[$id]['last_activity'] = $lastActivity;
            } else {
                $this->sessions[$id] = [
                    'id' => $id,
                    'payload' => $payload,
                    'last_activity' => $lastActivity,
                ];
            }

            return 1;
        }

        if (str_contains($sql, 'SET last_activity = ? WHERE id = ?')) {
            [$lastActivity, $id] = $bindings;

            if (!isset($this->sessions[$id])) {
                return 0;
            }

            $this->sessions[$id]['last_activity'] = $lastActivity;

            return 1;
        }

        if (
            str_contains($sql, 'DELETE')
            && str_contains($sql, 'WHERE id')
        ) {
            $id = $bindings[0];

            if (isset($this->sessions[$id])) {
                unset($this->sessions[$id]);

                return 1;
            }

            return 0;
        }

        if (
            str_contains($sql, 'DELETE')
            && str_contains($sql, 'last_activity')
        ) {
            $expireTime = $bindings[0];
            $count = 0;

            foreach ($this->sessions as $id => $session) {
                if ($session['last_activity'] < $expireTime) {
                    unset($this->sessions[$id]);
                    $count++;
                }
            }

            return $count;
        }

        return 0;
    }

    public function prepare(
        string $sql,
    ): StatementInterface {
        throw new RuntimeException('Not implemented');
    }

    public function lastInsertId(): int
    {
        return 0;
    }

    public function driverName(): string
    {
        return $this->driver;
    }

    public function supportsReturning(): bool
    {
        return false;
    }

    /**
     * Quotes like the named driver: a backtick for mysql, a double quote otherwise.
     */
    public function quoteIdentifier(
        string $identifier,
    ): string {
        $delimiter = $this->driver === 'mysql' ? '`' : '"';

        return $delimiter . str_replace($delimiter, $delimiter . $delimiter, $identifier) . $delimiter;
    }
}

/**
 * A connection that routes reads to a replica unless they run inside
 * onPrimary(), and records which reads reached the primary.
 */
class PrimaryRoutingConnection extends MockConnection implements PrimaryReadInterface
{
    private bool $onPrimary = false;

    /** @var list<string> */
    public array $primaryReads = [];

    /** @var list<string> */
    public array $replicaReads = [];

    public function onPrimary(
        callable $callback,
    ): mixed {
        $this->onPrimary = true;

        try {
            return $callback();
        } finally {
            $this->onPrimary = false;
        }
    }

    public function query(
        string $sql,
        array $bindings = [],
    ): array {
        if ($this->onPrimary) {
            $this->primaryReads[] = $sql;
        } else {
            $this->replicaReads[] = $sql;
        }

        return parent::query($sql, $bindings);
    }
}

function createDatabaseSessionConfig(): SessionConfig
{
    return new SessionConfig(new FakeConfigRepository([
        'session.lifetime' => 60,
    ]));
}

beforeEach(function (): void {
    $this->connection = new MockConnection();
    $this->clock = new FakeClock('2026-01-01 12:00:00 UTC');
    $this->handler = new DatabaseSessionHandler($this->connection, createDatabaseSessionConfig(), $this->clock);
});

describe('DatabaseSessionHandler', function (): void {
    it('implements SessionHandlerInterface', function (): void {
        expect($this->handler)->toBeInstanceOf(SessionHandlerInterface::class);
    });

    it('opens session successfully', function (): void {
        expect($this->handler->open('/tmp', 'PHPSESSID'))->toBeTrue();
    });

    it('closes session successfully', function (): void {
        expect($this->handler->close())->toBeTrue();
    });

    it('reads existing session data', function (): void {
        $this->connection->sessions['test-id'] = [
            'id' => 'test-id',
            'payload' => 'serialized-data',
            'last_activity' => $this->clock->now()->getTimestamp(),
        ];

        expect($this->handler->read('test-id'))->toBe('serialized-data');
    });

    it('returns empty string for missing session', function (): void {
        expect($this->handler->read('nonexistent'))->toBe('');
    });

    it('writes session data', function (): void {
        $result = $this->handler->write('new-session', 'session-data');

        expect($result)->toBeTrue()
            ->and($this->connection->sessions)->toHaveKey('new-session')
            ->and($this->connection->sessions['new-session']['payload'])->toBe('session-data');
    });

    it('updates existing session data', function (): void {
        $this->handler->write('update-id', 'first-data');
        $this->handler->write('update-id', 'second-data');

        expect($this->connection->sessions['update-id']['payload'])->toBe('second-data');
    });

    it('destroys existing session', function (): void {
        $this->handler->write('destroy-id', 'some-data');

        $result = $this->handler->destroy('destroy-id');

        expect($result)->toBeTrue()
            ->and($this->connection->sessions)->not->toHaveKey('destroy-id');
    });

    it('returns true when destroying missing session', function (): void {
        expect($this->handler->destroy('nonexistent'))->toBeTrue();
    });

    it('garbage collects expired sessions', function (): void {
        $this->connection->sessions['expired'] = [
            'id' => 'expired',
            'payload' => 'old-data',
            'last_activity' => $this->clock->now()->getTimestamp() - 7200,
        ];
        $this->connection->sessions['active'] = [
            'id' => 'active',
            'payload' => 'new-data',
            'last_activity' => $this->clock->now()->getTimestamp(),
        ];

        $this->handler->gc(3600);

        expect($this->connection->sessions)->not->toHaveKey('expired')
            ->and($this->connection->sessions)->toHaveKey('active');
    });

    it('returns count of deleted sessions from gc', function (): void {
        $this->connection->sessions['expired-1'] = [
            'id' => 'expired-1',
            'payload' => 'data',
            'last_activity' => $this->clock->now()->getTimestamp() - 7200,
        ];
        $this->connection->sessions['expired-2'] = [
            'id' => 'expired-2',
            'payload' => 'data',
            'last_activity' => $this->clock->now()->getTimestamp() - 7200,
        ];

        $count = $this->handler->gc(3600);

        expect($count)->toBe(2);
    });

    it('preserves recent sessions during gc', function (): void {
        $this->connection->sessions['recent'] = [
            'id' => 'recent',
            'payload' => 'fresh-data',
            'last_activity' => $this->clock->now()->getTimestamp() - 100,
        ];

        $count = $this->handler->gc(3600);

        expect($count)->toBe(0)
            ->and($this->connection->sessions)->toHaveKey('recent')
            ->and($this->connection->sessions['recent']['payload'])->toBe('fresh-data');
    });

    it('stores the clock time as last_activity on write', function (): void {
        $this->handler->write('clock-id', 'data');

        expect($this->connection->sessions['clock-id']['last_activity'])
            ->toBe($this->clock->now()->getTimestamp());
    });

    it('deletes sessions older than max lifetime relative to the clock', function (): void {
        $this->handler->write('aging-id', 'data');

        $countBefore = $this->handler->gc(3600);
        $this->clock->travel('+3601 seconds');
        $countAfter = $this->handler->gc(3600);

        expect($countBefore)->toBe(0)
            ->and($countAfter)->toBe(1)
            ->and($this->connection->sessions)->not->toHaveKey('aging-id');
    });

    it('writes a session row via a single upsert statement', function (): void {
        $this->handler->write('upsert-id', 'upsert-data');

        expect($this->connection->executedStatements)->toHaveCount(1);
    });

    it('updates the payload and last_activity for an existing session id without dropping the row', function (): void {
        $this->connection->sessions['existing-id'] = [
            'id' => 'existing-id',
            'payload' => 'original-data',
            'last_activity' => $this->clock->now()->getTimestamp() - 100,
        ];

        $this->handler->write('existing-id', 'updated-data');

        expect($this->connection->sessions['existing-id']['payload'])->toBe('updated-data')
            ->and($this->connection->executedStatements)->toHaveCount(1);
    });

    it('preserves a session row when two writes target the same id in sequence', function (): void {
        $this->handler->write('seq-id', 'first-write');
        $this->handler->write('seq-id', 'second-write');

        expect($this->connection->sessions)->toHaveKey('seq-id')
            ->and($this->connection->sessions['seq-id']['payload'])->toBe('second-write');
    });

    it('issues the MySQL upsert form for a MySQL connection', function (): void {
        $mysqlConnection = new MockConnection('mysql');
        $handler = new DatabaseSessionHandler($mysqlConnection, createDatabaseSessionConfig(), $this->clock);

        $handler->write('mysql-id', 'mysql-data');

        $sql = $mysqlConnection->executedStatements[0]['sql'];
        expect($sql)->toContain('ON DUPLICATE KEY UPDATE');
    });

    it('issues the ON CONFLICT upsert form for a Postgres or SQLite connection', function (): void {
        $pgsqlConnection = new MockConnection('pgsql');
        $handler = new DatabaseSessionHandler($pgsqlConnection, createDatabaseSessionConfig(), $this->clock);

        $handler->write('pgsql-id', 'pgsql-data');

        $sql = $pgsqlConnection->executedStatements[0]['sql'];
        expect($sql)->toContain('ON CONFLICT')
            ->and($sql)->toContain('DO UPDATE');

        $sqliteConnection = new MockConnection('sqlite');
        $sqliteHandler = new DatabaseSessionHandler($sqliteConnection, createDatabaseSessionConfig(), $this->clock);

        $sqliteHandler->write('sqlite-id', 'sqlite-data');

        $sqliteSql = $sqliteConnection->executedStatements[0]['sql'];
        expect($sqliteSql)->toContain('ON CONFLICT')
            ->and($sqliteSql)->toContain('DO UPDATE');
    });
});

describe('strict session ids', function (): void {
    it('validates an id whose row is within the lifetime', function (): void {
        $this->connection->sessions['live-id'] = [
            'id' => 'live-id',
            'payload' => 'data',
            'last_activity' => $this->clock->now()->getTimestamp() - 3600,
        ];

        expect($this->handler->validateId('live-id'))->toBeTrue();
    });

    it('rejects an id with no row', function (): void {
        expect($this->handler->validateId('never-issued'))->toBeFalse();
    });

    it('rejects an id whose last activity is older than the lifetime', function (): void {
        $this->connection->sessions['stale-id'] = [
            'id' => 'stale-id',
            'payload' => 'data',
            'last_activity' => $this->clock->now()->getTimestamp() - 3601,
        ];

        expect($this->handler->validateId('stale-id'))->toBeFalse();
    });

    it('updates only last_activity when updating the timestamp', function (): void {
        $this->connection->sessions['touched-id'] = [
            'id' => 'touched-id',
            'payload' => 'original-payload',
            'last_activity' => $this->clock->now()->getTimestamp() - 1800,
        ];

        $result = $this->handler->updateTimestamp('touched-id', 'ignored-payload');

        expect($result)->toBeTrue()
            ->and($this->connection->sessions['touched-id']['payload'])->toBe('original-payload')
            ->and($this->connection->sessions['touched-id']['last_activity'])
            ->toBe($this->clock->now()->getTimestamp())
            ->and($this->connection->executedStatements[0]['sql'])->not->toContain('payload');
    });

    it('does not insert a row when updating the timestamp', function (): void {
        $this->handler->updateTimestamp('never-issued', '');

        expect($this->connection->sessions)->not->toHaveKey('never-issued');
    });

    it('returns true when updating the timestamp of an id with no row', function (): void {
        expect($this->handler->updateTimestamp('never-issued', ''))->toBeTrue();
    });

    it('quotes the sessions table in every statement', function (string $driver, string $quoted): void {
        $connection = new MockConnection($driver);
        $handler = new DatabaseSessionHandler($connection, createDatabaseSessionConfig(), $this->clock);

        $handler->write('session-id', 'payload');
        $handler->read('session-id');
        $handler->validateId('session-id');
        $handler->updateTimestamp('session-id', 'payload');
        $handler->destroy('session-id');
        $handler->gc(60);

        $statements = [...array_column($connection->executedStatements, 'sql'), ...$connection->queriedStatements];

        expect($statements)->toHaveCount(6)
            ->and(array_filter($statements, fn (string $sql): bool => !str_contains($sql, " $quoted ")))->toBe([]);
    })->with([
        'mysql' => ['mysql', '`sessions`'],
        'pgsql' => ['pgsql', '"sessions"'],
    ])->issue(338);
});

describe('primary reads', function (): void {
    it('reads the session payload from the primary on a read/write split connection', function (): void {
        $connection = new PrimaryRoutingConnection();
        $connection->sessions['session-id'] = [
            'id' => 'session-id',
            'payload' => 'authenticated',
            'last_activity' => $this->clock->now()->getTimestamp(),
        ];
        $handler = new DatabaseSessionHandler($connection, createDatabaseSessionConfig(), $this->clock);

        expect($handler->read('session-id'))->toBe('authenticated')
            ->and($connection->primaryReads)->toHaveCount(1)
            ->and($connection->replicaReads)->toBe([]);
    })->issue(390);

    it('validates the session id against the primary on a read/write split connection', function (): void {
        $connection = new PrimaryRoutingConnection();
        $connection->sessions['session-id'] = [
            'id' => 'session-id',
            'payload' => 'authenticated',
            'last_activity' => $this->clock->now()->getTimestamp(),
        ];
        $handler = new DatabaseSessionHandler($connection, createDatabaseSessionConfig(), $this->clock);

        expect($handler->validateId('session-id'))->toBeTrue()
            ->and($connection->primaryReads)->toHaveCount(1)
            ->and($connection->replicaReads)->toBe([]);
    })->issue(390);

    it('reads directly from a connection that has no replicas to route around', function (): void {
        $this->connection->sessions['session-id'] = [
            'id' => 'session-id',
            'payload' => 'data',
            'last_activity' => $this->clock->now()->getTimestamp(),
        ];

        expect($this->connection)->not->toBeInstanceOf(PrimaryReadInterface::class)
            ->and($this->handler->read('session-id'))->toBe('data')
            ->and($this->handler->validateId('session-id'))->toBeTrue();
    })->issue(390);
});
