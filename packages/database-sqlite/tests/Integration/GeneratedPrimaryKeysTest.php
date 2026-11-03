<?php

declare(strict_types=1);

namespace Marko\Database\Sqlite\Tests\Integration;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Exceptions\UniqueConstraintViolationException;
use Marko\Database\Repository\Repository;
use Marko\Database\Sqlite\Introspection\SqliteIntrospector;
use Marko\Database\Sqlite\Tests\Fixtures\SqliteMigrations;
use Marko\Database\Sqlite\Tests\Fixtures\SqliteTestDatabase;

#[Table('generated_key_steps')]
class SqliteGeneratedKeyStep extends Entity
{
    /** @noinspection PhpUnused - Entity property accessed via reflection */
    #[Column(primaryKey: true, autoIncrement: true)]
    public int $id;

    /** @noinspection PhpUnused - Entity property accessed via reflection */
    #[Column]
    public string $label = '';
}

/**
 * @extends Repository<SqliteGeneratedKeyStep>
 */
class SqliteGeneratedKeyStepRepository extends Repository
{
    protected const string ENTITY_CLASS = SqliteGeneratedKeyStep::class;
}

#[Table('generated_key_links')]
class SqliteGeneratedKeyLink extends Entity
{
    /** @noinspection PhpUnused - Entity property accessed via reflection */
    #[Column(primaryKey: true)]
    public int $postId;

    /** @noinspection PhpUnused - Entity property accessed via reflection */
    #[Column(primaryKey: true)]
    public int $tagId;

    /** @noinspection PhpUnused - Entity property accessed via reflection */
    #[Column(length: 50)]
    public string $note = '';
}

describe('SQLite primary keys', function (): void {
    it(
        'reads generated primary keys back after insert and migrates a composite primary key table',
        function (string $kind): void {
            SqliteTestDatabase::using($kind, function (SqliteTestDatabase $database): void {
                $metadataFactory = new EntityMetadataFactory();
                SqliteMigrations::migrate($database, SqliteMigrations::schema(new SqliteGeneratedKeyStep()));
                $steps = new SqliteGeneratedKeyStepRepository(
                    $database->connection,
                    $metadataFactory,
                    new EntityHydrator($metadataFactory),
                );

                $first = new SqliteGeneratedKeyStep();
                $first->label = 'one';
                $second = new SqliteGeneratedKeyStep();
                $second->label = 'two';
                $steps->save($first);
                $steps->save($second);

                $link = SqliteMigrations::schema(new SqliteGeneratedKeyLink());
                SqliteMigrations::migrate($database, $link);
                $database->connection->execute('INSERT INTO generated_key_links (post_id, tag_id) VALUES (1, 1)');
                $database->connection->execute('INSERT INTO generated_key_links (post_id, tag_id) VALUES (1, 2)');
                $introspected = new SqliteIntrospector($database->connection)->getTable('generated_key_links');
                $keyColumns = array_map(
                    static fn ($column): string => $column->name,
                    array_filter($introspected->columns, static fn ($column): bool => $column->primaryKey),
                );

                expect($first->id)->toBe(1)
                    ->and($second->id)->toBe(2)
                    ->and($steps->find($second->id)?->label)->toBe('two')
                    ->and(array_values($keyColumns))->toBe(['post_id', 'tag_id'])
                    ->and(SqliteMigrations::diff($database, $link)->isEmpty())->toBeTrue()
                    ->and(fn () => $database->connection->execute(
                        'INSERT INTO generated_key_links (post_id, tag_id) VALUES (1, 2)',
                    ))->toThrow(UniqueConstraintViolationException::class);
            });
        },
    )->with([SqliteTestDatabase::FILE, SqliteTestDatabase::MEMORY]);
});
