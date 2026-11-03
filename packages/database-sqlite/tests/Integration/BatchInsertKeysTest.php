<?php

declare(strict_types=1);

namespace Marko\Database\Sqlite\Tests\Integration;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Repository\Repository;
use Marko\Database\Sqlite\Tests\Fixtures\SqliteMigrations;
use Marko\Database\Sqlite\Tests\Fixtures\SqliteTestDatabase;

#[Table('batch_insert_items')]
class SqliteBatchInsertItem extends Entity
{
    /** @noinspection PhpUnused - Entity property accessed via reflection */
    #[Column(primaryKey: true, autoIncrement: true)]
    public int $id;

    /** @noinspection PhpUnused - Entity property accessed via reflection */
    #[Column(length: 50, unique: true)]
    public string $label = '';
}

/**
 * @extends Repository<SqliteBatchInsertItem>
 */
class SqliteBatchInsertItemRepository extends Repository
{
    protected const string ENTITY_CLASS = SqliteBatchInsertItem::class;
}

describe('SQLite batch insert', function (): void {
    it('assigns each batch-inserted entity its own generated key on sqlite', function (string $kind): void {
        SqliteTestDatabase::using($kind, function (SqliteTestDatabase $database): void {
            $metadataFactory = new EntityMetadataFactory();
            SqliteMigrations::migrate($database, SqliteMigrations::schema(new SqliteBatchInsertItem()));
            $items = new SqliteBatchInsertItemRepository(
                $database->connection,
                $metadataFactory,
                new EntityHydrator($metadataFactory),
            );
            $database->connection->execute('INSERT INTO batch_insert_items (label) VALUES (?)', ['existing']);

            $batch = [];

            foreach (range(1, 100) as $number) {
                $item = new SqliteBatchInsertItem();
                // Labels sort differently from insertion order, so a reordered RETURNING cannot pass by luck.
                $item->label = 'item-' . strrev((string) $number);
                $batch[] = $item;
            }

            $items->insertBatch($batch);

            $labelsById = [];

            foreach ($database->connection->query('SELECT id, label FROM batch_insert_items') as $row) {
                $labelsById[$row['id']] = $row['label'];
            }

            $mismatches = array_filter(
                $batch,
                static fn (SqliteBatchInsertItem $item): bool => ($labelsById[$item->id] ?? null) !== $item->label,
            );

            expect($mismatches)->toBeEmpty()
                ->and($batch[0]->id)->toBe(2)
                ->and($batch[99]->id)->toBe(101);
        });
    })->with([SqliteTestDatabase::FILE, SqliteTestDatabase::MEMORY]);
});
