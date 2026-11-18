<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Schema;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\ForeignKey;
use Marko\Database\Attributes\Index;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Entity\SchemaBuilder;
use Marko\Database\Exceptions\EntityException;
use Marko\Database\Schema\SchemaRegistry;

#[Table('uniq_index_a')]
#[Index(['code'], name: 'idx_shared')]
class UniqIndexA extends Entity
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(primaryKey: true, autoIncrement: true)]
    public int $id;

    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(length: 100)]
    public string $code;
}

#[Table('uniq_index_b')]
#[Index(['code'], name: 'idx_shared')]
class UniqIndexB extends Entity
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(primaryKey: true, autoIncrement: true)]
    public int $id;

    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(length: 100)]
    public string $code;
}

#[Table('uniq_fk_a')]
#[ForeignKey(['team_id'], 'teams', ['id'], name: 'fk_shared')]
class UniqFkA extends Entity
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(primaryKey: true, autoIncrement: true)]
    public int $id;

    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column]
    public int $teamId;
}

#[Table('uniq_fk_b')]
#[ForeignKey(['team_id'], 'teams', ['id'], name: 'fk_shared')]
class UniqFkB extends Entity
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(primaryKey: true, autoIncrement: true)]
    public int $id;

    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column]
    public int $teamId;
}

#[Table('uniq_shared')]
#[Index(['team_id'], name: 'team_link')]
#[ForeignKey(['team_id'], 'teams', ['id'], name: 'team_link')]
class UniqSharedName extends Entity
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(primaryKey: true, autoIncrement: true)]
    public int $id;

    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column]
    public int $teamId;
}

#[Table('uniq_clash')]
#[Index(['code'], name: 'link')]
#[ForeignKey(['team_id'], 'teams', ['id'], name: 'link')]
class UniqClash extends Entity
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(primaryKey: true, autoIncrement: true)]
    public int $id;

    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(length: 100)]
    public string $code;

    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column]
    public int $teamId;
}

#[Table('uniq_derived_a')]
#[Index(['code'])]
class UniqDerivedA extends Entity
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(primaryKey: true, autoIncrement: true)]
    public int $id;

    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(length: 100)]
    public string $code;
}

#[Table('uniq_derived_b')]
#[Index(['code'])]
class UniqDerivedB extends Entity
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(primaryKey: true, autoIncrement: true)]
    public int $id;

    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(length: 100)]
    public string $code;
}

// Derives uniq_order_items_id_index
#[Table('uniq_order')]
#[Index(['items_id'])]
class UniqOrder extends Entity
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(primaryKey: true, autoIncrement: true)]
    public int $id;

    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column]
    public int $itemsId;
}

// Derives uniq_order_items_id_index
#[Table('uniq_order_items')]
#[Index(['id'])]
class UniqOrderItems extends Entity
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(primaryKey: true, autoIncrement: true)]
    public int $id;
}

#[Table('uniq_host')]
class UniqHost extends Entity
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(primaryKey: true, autoIncrement: true)]
    public int $id;
}

#[Table(extends: UniqHost::class)]
#[Index(['code'], name: 'idx_shared')]
class UniqHostIndexExtender extends Entity
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(length: 100)]
    public string $code;
}

// Derives fk_uniq_host_team_id
#[Table(extends: UniqHost::class)]
class UniqHostReferenceExtender extends Entity
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(references: 'teams.id')]
    public ?int $teamId;
}

#[Table('uniq_explicit_fk')]
#[ForeignKey(['team_id'], 'teams', ['id'], name: 'fk_uniq_host_team_id')]
class UniqExplicitFk extends Entity
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(primaryKey: true, autoIncrement: true)]
    public int $id;

    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column]
    public int $teamId;
}

beforeEach(function (): void {
    $this->registry = new SchemaRegistry(
        metadataFactory: new EntityMetadataFactory(),
        schemaBuilder: new SchemaBuilder(),
    );
});

describe('SchemaRegistry name uniqueness', function (): void {
    it('throws when two tables declare the same index name', function (): void {
        expect(fn () => $this->registry->registerEntities([UniqIndexA::class, UniqIndexB::class]))
            ->toThrow(EntityException::class, "Index 'idx_shared'");
    });

    it('throws when two tables declare the same foreign key name', function (): void {
        expect(fn () => $this->registry->registerEntities([UniqFkA::class, UniqFkB::class]))
            ->toThrow(EntityException::class, "Foreign key 'fk_shared'");
    });

    it('throws when two tables declare the same index name through registerEntity', function (): void {
        $this->registry->registerEntity(UniqIndexA::class);

        expect(fn () => $this->registry->registerEntity(UniqIndexB::class))
            ->toThrow(EntityException::class, "Index 'idx_shared'");
    });

    it('allows an index and a foreign key on the same columns to share a name', function (): void {
        $this->registry->registerEntities([UniqSharedName::class]);

        $table = $this->registry->getTable('uniq_shared');

        expect($table->indexes[0]->name)->toBe('team_link')
            ->and($table->foreignKeys[0]->name)->toBe('team_link');
    });

    it(
        'throws when a foreign key and an index on one table share a name but cover different columns',
        function (): void {
            expect(fn () => $this->registry->registerEntities([UniqClash::class]))
                ->toThrow(EntityException::class, "'link'");
        },
    );

    it('allows the same columns to be indexed on two tables with derived names', function (): void {
        $this->registry->registerEntities([UniqDerivedA::class, UniqDerivedB::class]);

        expect($this->registry->getTable('uniq_derived_a')->indexes[0]->name)->toBe('uniq_derived_a_code_index')
            ->and($this->registry->getTable('uniq_derived_b')->indexes[0]->name)->toBe('uniq_derived_b_code_index');
    });

    it('names both tables and entities in the duplicate name error', function (): void {
        try {
            $this->registry->registerEntities([UniqIndexA::class, UniqIndexB::class]);
            $this->fail('Expected EntityException');
        } catch (EntityException $exception) {
            expect($exception->getMessage())
                ->toContain('idx_shared')
                ->toContain("'uniq_index_a'")
                ->toContain("'uniq_index_b'")
                ->toContain(UniqIndexA::class)
                ->toContain(UniqIndexB::class);
        }
    });

    it('names the extender class when the duplicate name comes from an extender', function (): void {
        try {
            $this->registry->registerEntities([UniqIndexA::class, UniqHost::class, UniqHostIndexExtender::class]);
            $this->fail('Expected EntityException');
        } catch (EntityException $exception) {
            expect($exception->getMessage())
                ->toContain(UniqIndexA::class)
                ->toContain(UniqHostIndexExtender::class)
                ->not->toContain("('" . UniqHost::class . "')");
        }
    });

    it(
        'names the extender class when a duplicate foreign key name comes from an extender reference',
        function (): void {
            try {
                $this->registry->registerEntities(
                    [UniqExplicitFk::class, UniqHost::class, UniqHostReferenceExtender::class],
                );
                $this->fail('Expected EntityException');
            } catch (EntityException $exception) {
                expect($exception->getMessage())
                    ->toContain('fk_uniq_host_team_id')
                    ->toContain(UniqExplicitFk::class)
                    ->toContain(UniqHostReferenceExtender::class);
            }
        },
    );

    it('throws when two derived names collide across tables', function (): void {
        expect(fn () => $this->registry->registerEntities([UniqOrder::class, UniqOrderItems::class]))
            ->toThrow(EntityException::class, "Index 'uniq_order_items_id_index'");
    });

    it('does not report a table re-registered under the same name as a duplicate', function (): void {
        $this->registry->registerEntity(UniqIndexA::class);
        $this->registry->registerEntity(UniqIndexA::class);
        $this->registry->registerEntities([UniqIndexA::class, UniqFkA::class]);
        $this->registry->registerEntities([UniqIndexA::class, UniqFkA::class]);

        expect($this->registry->getTableNames())->toBe(['uniq_index_a', 'uniq_fk_a']);
    });
});
