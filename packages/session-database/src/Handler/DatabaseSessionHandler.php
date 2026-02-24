<?php

declare(strict_types=1);

namespace Marko\Session\Database\Handler;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Session\Contracts\SessionHandlerInterface;

readonly class DatabaseSessionHandler implements SessionHandlerInterface
{
    public function __construct(
        private ConnectionInterface $connection,
    ) {}

    public function open(
        string $path,
        string $name,
    ): bool {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(
        string $id,
    ): string|false {
        $results = $this->connection->query(
            'SELECT payload FROM sessions WHERE id = ?',
            [$id],
        );

        if ($results === []) {
            return '';
        }

        return $results[0]['payload'];
    }

    public function write(
        string $id,
        string $data,
    ): bool {
        $this->connection->execute(
            'DELETE FROM sessions WHERE id = ?',
            [$id],
        );

        $this->connection->execute(
            'INSERT INTO sessions (id, payload, last_activity) VALUES (?, ?, ?)',
            [$id, $data, time()],
        );

        return true;
    }

    public function destroy(
        string $id,
    ): bool {
        $this->connection->execute(
            'DELETE FROM sessions WHERE id = ?',
            [$id],
        );

        return true;
    }

    public function gc(
        int $max_lifetime,
    ): int|false {
        $expireTime = time() - $max_lifetime;

        return $this->connection->execute(
            'DELETE FROM sessions WHERE last_activity < ?',
            [$expireTime],
        );
    }
}
