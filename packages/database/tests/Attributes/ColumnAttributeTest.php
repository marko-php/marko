<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Attributes;

use Marko\Database\Attributes\Column;
use Marko\Database\Entity\Entity;
use ReflectionProperty;

class ColumnTestEntity extends Entity
{
    #[Column]
    public int $id;

    #[Column('user_name')]
    public string $name;
}

class PrimaryKeyTestEntity extends Entity
{
    #[Column(primaryKey: true)]
    public int $id;
}

class AutoIncrementTestEntity extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public int $id;
}

class VarcharLengthTestEntity extends Entity
{
    #[Column(length: 100)]
    public string $name;

    #[Column(length: 255)]
    public string $email;
}

class ExplicitTypeTestEntity extends Entity
{
    #[Column(type: 'text')]
    public string $content;

    #[Column(type: 'decimal', length: 10)]
    public float $price;
}

class UniqueColumnTestEntity extends Entity
{
    #[Column(unique: true)]
    public string $email;
}

class DefaultValueTestEntity extends Entity
{
    #[Column(default: 'active')]
    public string $status;

    #[Column(default: 0)]
    public int $count;

    #[Column(default: true)]
    public bool $enabled;
}

class ForeignKeyReferenceTestEntity extends Entity
{
    #[Column(references: 'users.id')]
    public int $userId;
}

class ForeignKeyActionsTestEntity extends Entity
{
    #[Column(references: 'users.id', onDelete: 'CASCADE', onUpdate: 'SET NULL')]
    public int $userId;
}

it('creates #[Column] attribute with optional name parameter', function (): void {
    // Test column without explicit name (uses property name)
    $idProperty = new ReflectionProperty(ColumnTestEntity::class, 'id');
    $idAttributes = $idProperty->getAttributes(Column::class);
    $idColumn = $idAttributes[0]->newInstance();

    // Test column with explicit name
    $nameProperty = new ReflectionProperty(ColumnTestEntity::class, 'name');
    $nameAttributes = $nameProperty->getAttributes(Column::class);
    $nameColumn = $nameAttributes[0]->newInstance();

    expect($idAttributes)->toHaveCount(1)
        ->and($idColumn)->toBeInstanceOf(Column::class)
        ->and($idColumn->name)->toBeNull()
        ->and($nameAttributes)->toHaveCount(1)
        ->and($nameColumn->name)->toBe('user_name');
});

it('supports #[Column] primaryKey parameter', function (): void {
    $property = new ReflectionProperty(PrimaryKeyTestEntity::class, 'id');
    $attributes = $property->getAttributes(Column::class);

    $column = $attributes[0]->newInstance();
    expect($column->primaryKey)->toBeTrue();
});

it('supports #[Column] autoIncrement parameter', function (): void {
    $property = new ReflectionProperty(AutoIncrementTestEntity::class, 'id');
    $attributes = $property->getAttributes(Column::class);

    $column = $attributes[0]->newInstance();
    expect($column->autoIncrement)->toBeTrue()
        ->and($column->primaryKey)->toBeTrue();
});

it('supports #[Column] length parameter for varchar', function (): void {
    $nameProperty = new ReflectionProperty(VarcharLengthTestEntity::class, 'name');
    $nameColumn = $nameProperty->getAttributes(Column::class)[0]->newInstance();

    $emailProperty = new ReflectionProperty(VarcharLengthTestEntity::class, 'email');
    $emailColumn = $emailProperty->getAttributes(Column::class)[0]->newInstance();

    expect($nameColumn->length)->toBe(100)
        ->and($emailColumn->length)->toBe(255);
});

it('supports #[Column] type parameter for explicit type override', function (): void {
    $contentProperty = new ReflectionProperty(ExplicitTypeTestEntity::class, 'content');
    $contentColumn = $contentProperty->getAttributes(Column::class)[0]->newInstance();

    $priceProperty = new ReflectionProperty(ExplicitTypeTestEntity::class, 'price');
    $priceColumn = $priceProperty->getAttributes(Column::class)[0]->newInstance();

    expect($contentColumn->type)->toBe('text')
        ->and($priceColumn->type)->toBe('decimal')
        ->and($priceColumn->length)->toBe(10);
});

it('supports #[Column] unique parameter', function (): void {
    $property = new ReflectionProperty(UniqueColumnTestEntity::class, 'email');
    $column = $property->getAttributes(Column::class)[0]->newInstance();
    expect($column->unique)->toBeTrue();
});

it('supports #[Column] default parameter', function (): void {
    $statusProperty = new ReflectionProperty(DefaultValueTestEntity::class, 'status');
    $statusColumn = $statusProperty->getAttributes(Column::class)[0]->newInstance();

    $countProperty = new ReflectionProperty(DefaultValueTestEntity::class, 'count');
    $countColumn = $countProperty->getAttributes(Column::class)[0]->newInstance();

    $enabledProperty = new ReflectionProperty(DefaultValueTestEntity::class, 'enabled');
    $enabledColumn = $enabledProperty->getAttributes(Column::class)[0]->newInstance();

    expect($statusColumn->default)->toBe('active')
        ->and($countColumn->default)->toBe(0)
        ->and($enabledColumn->default)->toBeTrue();
});

it('supports #[Column] references parameter for foreign keys', function (): void {
    $property = new ReflectionProperty(ForeignKeyReferenceTestEntity::class, 'userId');
    $column = $property->getAttributes(Column::class)[0]->newInstance();
    expect($column->references)->toBe('users.id');
});

it('supports #[Column] onDelete and onUpdate parameters', function (): void {
    $property = new ReflectionProperty(ForeignKeyActionsTestEntity::class, 'userId');
    $column = $property->getAttributes(Column::class)[0]->newInstance();
    expect($column->references)->toBe('users.id')
        ->and($column->onDelete)->toBe('CASCADE')
        ->and($column->onUpdate)->toBe('SET NULL');
});
