<?php

declare(strict_types=1);

namespace Marko\Session\Database\Tests\Integration\Sqlite;

use Marko\Database\Config\DatabaseTimezoneConfig;
use Marko\Database\Sqlite\Introspection\SqliteIntrospector;
use Marko\Database\Sqlite\Sql\SqliteGenerator;
use Marko\Database\Sqlite\Tests\Fixtures\SqliteTestDatabase;
use Marko\Session\Database\Tests\Fixtures\SessionTable;

/*
 * DatabaseSessionHandler against a real SQLite database, through its INSERT ... ON CONFLICT (id) DO UPDATE upsert.
 * The sessions table is built from the DatabaseSession entity through SqliteGenerator, as db:migrate builds it.
 * Each test gets its own temp file, so these run in plain `composer test`, without the integration-services group.
 */

beforeEach(function (): void {
    $this->database = new SqliteTestDatabase(SqliteTestDatabase::FILE);
    $this->connection = $this->database->connection;
    SessionTable::create($this->connection, new SqliteGenerator(DatabaseTimezoneConfig::fromName('UTC')));
    $this->clock = SessionTable::clock();
    $this->handler = SessionTable::handler($this->connection, $this->clock);
});

afterEach(function (): void {
    $this->database->dispose();
});

describe('DatabaseSessionHandler on SQLite', function (): void {
    it('writes reads and destroys database sessions on sqlite', function (): void {
        $this->handler->write('session-one', 'first');
        $this->clock->travel('+1 minute');
        $this->handler->write('session-one', 'second');

        $rows = $this->connection->query('SELECT id, payload, last_activity FROM sessions');
        $unknown = $this->handler->read('missing');
        $known = $this->handler->validateId('session-one');

        $this->handler->write('doomed', 'gone');
        $this->handler->destroy('doomed');

        expect($this->handler->read('session-one'))->toBe('second')
            ->and($unknown)->toBe('')
            ->and($known)->toBeTrue()
            ->and($rows)->toHaveCount(1)
            ->and((int) $rows[0]['last_activity'])->toBe($this->clock->now()->getTimestamp())
            ->and($this->handler->read('doomed'))->toBe('')
            ->and($this->connection->query('SELECT id FROM sessions'))->toBe([['id' => 'session-one']]);
    });

    it('refreshes last activity and garbage-collects expired rows on sqlite', function (): void {
        $this->handler->write('stale', 'old');
        $this->clock->travel('+3 hours');
        $this->handler->write('fresh', 'new');
        $this->handler->updateTimestamp('fresh', 'ignored');
        $this->handler->updateTimestamp('missing', 'ignored');

        $deleted = $this->handler->gc(SessionTable::LIFETIME_MINUTES * 60);

        expect($deleted)->toBe(1)
            ->and($this->connection->query('SELECT id, payload FROM sessions'))
            ->toBe([['id' => 'fresh', 'payload' => 'new']]);
    });

    it('diffs a sessions table created from the entity as empty on sqlite', function (): void {
        $diff = SessionTable::diff(new SqliteIntrospector($this->connection));

        expect(new SqliteGenerator(DatabaseTimezoneConfig::fromName('UTC'))->generateUp($diff))->toBeEmpty();
    });
});
