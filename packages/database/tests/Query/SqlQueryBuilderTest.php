<?php

declare(strict_types=1);

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Exceptions\LockException;
use Marko\Database\Query\QueryBuilderFactoryInterface;
use Marko\Database\Query\QueryBuilderInterface;
use Marko\Database\Tests\Query\BracketDialectQueryBuilder;
use Marko\Database\Tests\Query\RecordingQueryConnection;

function bracketBuilder(
    RecordingQueryConnection $connection,
): BracketDialectQueryBuilder {
    $factory = new readonly class ($connection) implements QueryBuilderFactoryInterface
    {
        public function __construct(
            private ConnectionInterface $connection,
        ) {}

        public function create(): QueryBuilderInterface
        {
            return new BracketDialectQueryBuilder($this->connection, $this);
        }
    };

    return new BracketDialectQueryBuilder($connection, $factory);
}

describe('SqlQueryBuilder', function (): void {
    it('compiles every select clause in the shared base through a test dialect', function (): void {
        $connection = new RecordingQueryConnection();

        bracketBuilder($connection)
            ->table('users')
            ->select('users.name', 'COUNT(*) AS total')
            ->join('posts', 'users.id', '=', 'posts.user_id')
            ->where('status', '=', 'active')
            ->groupBy('users.name')
            ->having('total > ?', [1])
            ->orderBy('users.name', 'desc')
            ->limit(10)
            ->offset(5)
            ->get();

        expect($connection->lastQuerySql)->toBe(
            'SELECT [users.name], COUNT(*) AS [total] FROM [users]'
            . ' INNER JOIN [posts] ON [users.id] = [posts.user_id]'
            . ' WHERE [status] = ? GROUP BY [users.name] HAVING total > ?'
            . ' ORDER BY [users.name] DESC LIMIT 10 OFFSET 5',
        )->and($connection->lastQueryBindings)->toBe(['active', 1]);
    });

    it('delegates identifier quoting to the dialect hook', function (): void {
        $connection = new RecordingQueryConnection();

        bracketBuilder($connection)->table('users')->where('email', '=', 'a@b.c')->delete();

        expect($connection->lastExecuteSql)->toBe('DELETE FROM [users] WHERE [email] = ?');
    });

    it('delegates json path compilation to the dialect hook', function (): void {
        $connection = new RecordingQueryConnection();

        bracketBuilder($connection)
            ->table('users')
            ->where('data->>city', '=', 'Paris')
            ->whereJsonContains('data->tags', ['a'])
            ->whereJsonExists('data->profile->age')
            ->get();

        expect($connection->lastQuerySql)->toBe(
            'SELECT * FROM [users] WHERE JSON_AT([data], city) = ?'
            . ' AND CONTAINS(JSON_AT([data], tags), ?) AND HAS_PATH(JSON_AT([data], profile.age))',
        );
    });

    it('delegates the upsert conflict clause to the dialect hook', function (): void {
        $connection = new RecordingQueryConnection();

        bracketBuilder($connection)
            ->table('users')
            ->upsert([['email' => 'a@b.c', 'name' => 'A']], ['email'], ['name']);

        expect($connection->lastExecuteSql)->toBe(
            'INSERT INTO [users] ([email], [name]) VALUES (?, ?) CONFLICT([email]) SET [name]',
        );
    });

    it('validates lock modifiers in the base before asking the dialect for the lock clause', function (): void {
        $connection = new RecordingQueryConnection();

        bracketBuilder($connection)->table('users')->lockForUpdate()->skipLocked()->get();

        expect($connection->lastQuerySql)->toBe('SELECT * FROM [users] LOCK_UPDATE SKIP LOCKED')
            ->and(fn () => bracketBuilder($connection)->table('users')->lockForUpdate()->skipLocked()->noWait()->get())
            ->toThrow(LockException::class)
            ->and(fn () => bracketBuilder($connection)->table('users')->skipLocked()->get())
            ->toThrow(LockException::class)
            ->and(
                fn () => bracketBuilder(new RecordingQueryConnection(open: false))->table(
                    'users',
                )->sharedLock()->get(),
            )
            ->toThrow(LockException::class)
            ->and(fn () => bracketBuilder($connection)->table('users')->sharedLock()->count())
            ->toThrow(LockException::class);
    });

    it('returns the key from the dialect insert hook', function (): void {
        $connection = new RecordingQueryConnection();

        $id = bracketBuilder($connection)->table('users')->insert(['name' => 'A']);

        expect($id)->toBe(7)
            ->and($connection->lastExecuteSql)->toBe('INSERT INTO [users] ([name]) VALUES (?)');
    });
});
