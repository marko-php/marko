<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Tests\Integration;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Index;
use Marko\Database\Attributes\Table;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Diff\DiffCalculator;
use Marko\Database\Diff\SchemaDiff;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Entity\SchemaBuilder;
use Marko\Database\Migration\DataMigration;
use Marko\Database\PgSql\Connection\PgSqlConnection;
use Marko\Database\PgSql\Introspection\PgSqlIntrospector;
use Marko\Database\PgSql\Sql\PgSqlGenerator;
use Marko\Database\PgSql\Sql\PgSqlIdentifier;
use Marko\Database\PgSql\Tests\Fixtures\IntegrationDatabase;
use Marko\Database\Repository\Repository;
use Marko\Database\Schema\Column as SchemaColumn;
use Marko\Database\Schema\Table as SchemaTable;
use Marko\Database\Testing\DatabaseTestHelper;

/*
 * Reserved-word, mixed-case and delimiter identifiers against a real PostgreSQL server. Tables are created from
 * entities through SchemaBuilder and PgSqlGenerator, as db:migrate creates them, and every statement after that
 * comes from Repository, DataMigration or DatabaseTestHelper, so a name any of them forgets to quote fails here
 * (a syntax error for a reserved word, an unknown column for a mixed-case name folded to lower case). Set
 * MARKO_TEST_PGSQL_HOST (and optionally _PORT, _DATABASE, _USERNAME, _PASSWORD) to enable; the tests skip
 * otherwise. The tests create and drop the ident_settings, ident"quoted and ident$$serial tables.
 *
 * Settings come from tests/Fixtures/IntegrationDatabase. With MARKO_INTEGRATION_REQUIRED set (CI), a missing
 * host fails instead of skipping. Part of the integration-services group.
 */

#[Table('ident_settings')]
#[Index(['group', 'order'])]
class PgSqlReservedWordSetting extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column(unique: true)]
    public string $key = '';

    #[Column]
    public string $group = '';

    #[Column]
    public int $order = 0;

    #[Column(name: 'displayName')]
    public string $displayName = '';
}

/**
 * @extends Repository<PgSqlReservedWordSetting>
 */
class PgSqlReservedWordSettingRepository extends Repository
{
    protected const string ENTITY_CLASS = PgSqlReservedWordSetting::class;
}

#[Table('ident"quoted')]
class PgSqlDelimiterNamedRow extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column(name: 'la"bel', unique: true)]
    public string $label = '';
}

/**
 * @extends Repository<PgSqlDelimiterNamedRow>
 */
class PgSqlDelimiterNamedRowRepository extends Repository
{
    protected const string ENTITY_CLASS = PgSqlDelimiterNamedRow::class;
}

/**
 * A data migration exposing its insert/update/delete helpers.
 */
class PgSqlReservedWordDataMigration extends DataMigration
{
    public function up(ConnectionInterface $connection): void {}

    public function down(ConnectionInterface $connection): void {}

    public function callInsert(
        ConnectionInterface $connection,
        string $table,
        array $data,
    ): int {
        return $this->insert($connection, $table, $data);
    }

    public function callUpdate(
        ConnectionInterface $connection,
        string $table,
        array $data,
        array $where,
    ): int {
        return $this->update($connection, $table, $data, $where);
    }

    public function callDelete(
        ConnectionInterface $connection,
        string $table,
        array $where,
    ): int {
        return $this->delete($connection, $table, $where);
    }
}

function pgsqlReservedWordSetting(
    string $key,
    string $group,
    int $order,
): PgSqlReservedWordSetting {
    $setting = new PgSqlReservedWordSetting();
    $setting->key = $key;
    $setting->group = $group;
    $setting->order = $order;
    $setting->displayName = ucfirst($key);

    return $setting;
}

pest()->group('integration-services');

beforeEach(function (): void {
    $config = IntegrationDatabase::config();

    if ($config === null) {
        $this->markTestSkipped(IntegrationDatabase::SKIP_REASON);
    }

    $this->connection = new PgSqlConnection($config);
    $this->dropTables = function (): void {
        foreach (['ident_settings', 'ident"quoted', 'ident$$serial'] as $table) {
            $this->connection->execute('DROP TABLE IF EXISTS ' . PgSqlIdentifier::quote($table));
        }
    };
    ($this->dropTables)();

    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator($metadataFactory);
    $this->generator = new PgSqlGenerator();

    $this->run = function (array $statements): void {
        foreach ($statements as $statement) {
            $this->connection->execute($statement);
        }
    };

    foreach ([PgSqlReservedWordSetting::class, PgSqlDelimiterNamedRow::class] as $entityClass) {
        $table = new SchemaBuilder()->build($metadataFactory->parse($entityClass));
        ($this->run)($this->generator->generateUp(new SchemaDiff(tablesToCreate: [$table->name => $table])));
    }

    $this->settingsTable = new SchemaBuilder()->build($metadataFactory->parse(PgSqlReservedWordSetting::class));
    $this->settings = new PgSqlReservedWordSettingRepository($this->connection, $metadataFactory, $hydrator);
    $this->delimiterRows = new PgSqlDelimiterNamedRowRepository($this->connection, $metadataFactory, $hydrator);
});

afterEach(function (): void {
    if (isset($this->connection)) {
        ($this->dropTables)();
        $this->connection->disconnect();
    }
});

describe('PostgreSQL reserved-word identifiers', function (): void {
    it(
        'round-trips an entity with reserved-word columns through save, find, findOneBy, findBy, update, exists and delete',
        function (): void {
            $from = pgsqlReservedWordSetting('from', 'mail', 1);
            $host = pgsqlReservedWordSetting('host', 'smtp', 2);
            $this->settings->save($from);
            $this->settings->save($host);

            expect($this->settings->find($from->id)?->displayName)->toBe('From')
                ->and($this->settings->findOneBy(['key' => 'host'])?->group)->toBe('smtp')
                ->and($this->settings->findBy(['group' => 'mail', 'order' => 1])->count())->toBe(1)
                ->and($this->settings->findBy(['displayName' => 'Host'])->count())->toBe(1)
                ->and($this->settings->findAll()->count())->toBe(2)
                ->and($this->settings->count())->toBe(2);

            $from->group = 'smtp';
            $from->order = 3;
            $from->displayName = 'Sender';
            $this->settings->save($from);

            expect($this->settings->find($from->id)?->displayName)->toBe('Sender')
                ->and($this->settings->findBy(['group' => 'smtp'])->count())->toBe(2)
                ->and($this->settings->exists($from->id))->toBeTrue()
                ->and($this->settings->existsBy(['key' => 'from', 'order' => 3]))->toBeTrue();

            $this->settings->delete($from);

            expect($this->settings->exists($from->id))->toBeFalse()
                ->and($this->settings->existsBy(['group' => 'mail']))->toBeFalse();
        },
    );

    it('inserts a batch of entities with reserved-word columns', function (): void {
        $settings = [
            pgsqlReservedWordSetting('a', 'batch', 1),
            pgsqlReservedWordSetting('b', 'batch', 2),
            pgsqlReservedWordSetting('c', 'batch', 3),
        ];

        $this->settings->insertBatch($settings);

        expect(array_map(fn (PgSqlReservedWordSetting $setting): ?string => $this->settings->find(
            $setting->id,
        )?->key, $settings))->toBe(['a', 'b', 'c'])
            ->and($this->settings->findBy(['group' => 'batch'])->count())->toBe(3);
    });

    it('writes reserved-word columns through DataMigration and DatabaseTestHelper', function (): void {
        $migration = new PgSqlReservedWordDataMigration();
        $helper = new DatabaseTestHelper($this->connection);

        $migration->callInsert($this->connection, 'ident_settings', [
            ['key' => 'one', 'group' => 'data', 'order' => 1, 'displayName' => 'One'],
            ['key' => 'two', 'group' => 'data', 'order' => 2, 'displayName' => 'Two'],
        ]);
        $migration->callUpdate(
            $this->connection,
            'ident_settings',
            ['order' => 9],
            ['key' => 'one', 'group' => 'data'],
        );
        $migration->callDelete($this->connection, 'ident_settings', ['key' => 'two']);
        $helper->seedTable(
            'ident_settings',
            [['key' => 'three', 'group' => 'seed', 'order' => 3, 'displayName' => 'Three']],
        );

        expect($this->settings->findOneBy(['key' => 'one'])?->order)->toBe(9)
            ->and($this->settings->existsBy(['key' => 'two']))->toBeFalse()
            ->and($helper->getTableRowCount('ident_settings'))->toBe(2);

        $helper->truncateTable('ident_settings');

        expect($helper->getTableRowCount('ident_settings'))->toBe(0);
    });

    it('creates and uses a table whose names contain the delimiter', function (): void {
        $row = new PgSqlDelimiterNamedRow();
        $row->label = 'say "hi"';

        $this->delimiterRows->save($row);
        $row->label = 'say "bye"';
        $this->delimiterRows->save($row);

        expect($this->delimiterRows->find($row->id)?->label)->toBe('say "bye"')
            ->and($this->delimiterRows->findOneBy(['label' => 'say "bye"'])?->id)->toBe($row->id)
            ->and($this->delimiterRows->count())->toBe(1);
    });

    it('rolls back a renamed index on reserved-word columns', function (): void {
        $this->connection->execute(
            'ALTER INDEX "ident_settings_group_order_index" RENAME TO "ident_settings_legacy_index"',
        );
        $introspector = new PgSqlIntrospector($this->connection);
        $diff = new DiffCalculator()->calculate(
            ['ident_settings' => $this->settingsTable],
            ['ident_settings' => $introspector->getTable('ident_settings')],
        );

        ($this->run)($this->generator->generateUp($diff));
        ($this->run)($this->generator->generateDown($diff));

        $indexes = array_column(
            array_map(
                static fn ($index): array => [$index->name, $index->columns],
                $introspector->getIndexes('ident_settings'),
            ),
            1,
            0,
        );

        expect($indexes['ident_settings_legacy_index'] ?? null)->toBe(['group', 'order'])
            ->and($indexes)->not->toHaveKey('ident_settings_group_order_index');
    });

    it('widens an auto-increment key whose table name contains $$ in one DO block', function (): void {
        $serialTable = static fn (string $type): SchemaTable => new SchemaTable(
            name: 'ident$$serial',
            columns: [new SchemaColumn(name: 'id', type: $type, primaryKey: true, autoIncrement: true)],
        );
        ($this->run)($this->generator->generateUp(
            new SchemaDiff(tablesToCreate: ['ident$$serial' => $serialTable('integer')]),
        ));

        $introspector = new PgSqlIntrospector($this->connection);
        $diff = new DiffCalculator()->calculate(
            ['ident$$serial' => $serialTable('bigint')],
            ['ident$$serial' => $introspector->getTable('ident$$serial')],
        );
        $statements = $this->generator->generateUp($diff);

        ($this->run)($statements);

        $sequenceType = $this->connection->query(
            'SELECT data_type FROM information_schema.sequences WHERE sequence_name = ?',
            ['ident$$serial_id_seq'],
        );

        expect($statements[0])->toStartWith("DO \$marko\$\n")
            ->and($introspector->getTable('ident$$serial')?->columns[0]->type)->toBe('bigint')
            ->and($sequenceType[0]['data_type'] ?? null)->toBe('bigint');
    });
});
