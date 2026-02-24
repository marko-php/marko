<?php

declare(strict_types=1);

namespace Marko\Session\Database\Tests\Unit;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Session\Contracts\SessionHandlerInterface;
use Marko\Session\Database\Handler\DatabaseSessionHandler;
use RuntimeException;

class MockConnection implements ConnectionInterface
{
    /** @var array<string, array{id: string, payload: string, last_activity: int}> */
    public array $sessions = [];

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
        if (str_contains($sql, 'SELECT') && str_contains($sql, 'WHERE id')) {
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
        if (str_contains($sql, 'INSERT')) {
            $this->sessions[$bindings[0]] = [
                'id' => $bindings[0],
                'payload' => $bindings[1],
                'last_activity' => $bindings[2],
            ];

            return 1;
        }

        if (str_contains($sql, 'DELETE') && str_contains($sql, 'WHERE id')) {
            $id = $bindings[0];

            if (isset($this->sessions[$id])) {
                unset($this->sessions[$id]);

                return 1;
            }

            return 0;
        }

        if (str_contains($sql, 'DELETE') && str_contains($sql, 'last_activity')) {
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
}

beforeEach(function (): void {
    $this->connection = new MockConnection();
    $this->handler = new DatabaseSessionHandler($this->connection);
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
            'last_activity' => time(),
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
            'last_activity' => time() - 7200,
        ];
        $this->connection->sessions['active'] = [
            'id' => 'active',
            'payload' => 'new-data',
            'last_activity' => time(),
        ];

        $this->handler->gc(3600);

        expect($this->connection->sessions)->not->toHaveKey('expired')
            ->and($this->connection->sessions)->toHaveKey('active');
    });

    it('returns count of deleted sessions from gc', function (): void {
        $this->connection->sessions['expired-1'] = [
            'id' => 'expired-1',
            'payload' => 'data',
            'last_activity' => time() - 7200,
        ];
        $this->connection->sessions['expired-2'] = [
            'id' => 'expired-2',
            'payload' => 'data',
            'last_activity' => time() - 7200,
        ];

        $count = $this->handler->gc(3600);

        expect($count)->toBe(2);
    });

    it('preserves recent sessions during gc', function (): void {
        $this->connection->sessions['recent'] = [
            'id' => 'recent',
            'payload' => 'fresh-data',
            'last_activity' => time() - 100,
        ];

        $count = $this->handler->gc(3600);

        expect($count)->toBe(0)
            ->and($this->connection->sessions)->toHaveKey('recent')
            ->and($this->connection->sessions['recent']['payload'])->toBe('fresh-data');
    });
});
