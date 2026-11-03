<?php

declare(strict_types=1);

namespace Marko\Database\Sqlite\Tests\Fixtures;

use Marko\Database\Config\DatabaseConfig;
use Marko\Database\Sqlite\Connection\SqliteConnection;

/**
 * A SQLite database for one test: a uniquely named temp file or a private in-memory database. Every
 * test makes its own, so the tests are safe to run in parallel. dispose() removes the file with its
 * -wal and -shm companions.
 */
readonly class SqliteTestDatabase
{
    public const string FILE = 'file';

    public const string MEMORY = 'memory';

    public string $path;

    public SqliteConnection $connection;

    /**
     * @param array<string, int|string> $pragmas
     */
    public function __construct(
        public string $kind,
        array $pragmas = [],
    ) {
        $this->path = $kind === self::FILE
            ? sys_get_temp_dir() . '/marko-sqlite-it-' . bin2hex(random_bytes(8)) . '.db'
            : ':memory:';
        $this->connection = $this->connect($pragmas);
    }

    /**
     * Run the callback with a fresh database and always dispose of it afterwards.
     *
     * @param callable(self): mixed $callback
     */
    public static function using(
        string $kind,
        callable $callback,
    ): mixed {
        $database = new self($kind);

        try {
            return $callback($database);
        } finally {
            $database->dispose();
        }
    }

    /**
     * A further connection to the same file, for example to contend with the first one.
     *
     * @param array<string, int|string> $pragmas
     */
    public function connect(
        array $pragmas = [],
    ): SqliteConnection {
        $config = ['driver' => 'sqlite', 'database' => $this->path];

        if ($pragmas !== []) {
            $config['sqlite'] = ['pragmas' => $pragmas];
        }

        return new SqliteConnection(DatabaseConfig::fromArray($config));
    }

    public function dispose(): void
    {
        $this->connection->disconnect();

        if ($this->kind !== self::FILE) {
            return;
        }

        foreach ([$this->path, $this->path . '-wal', $this->path . '-shm', $this->path . '-journal'] as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }
}
