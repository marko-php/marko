<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Entity;

use Marko\Database\Entity\ColumnMetadata;
use Marko\Database\Entity\EntityMetadata;
use Marko\Database\Entity\IndexMetadata;
use Marko\Database\Entity\PropertyMetadata;

it('creates EntityMetadata class to hold parsed attribute data', function (): void {
    $columns = [
        new ColumnMetadata(
            name: 'id',
            type: 'INT',
            nullable: false,
            primaryKey: true,
            autoIncrement: true,
        ),
        new ColumnMetadata(
            name: 'title',
            type: 'VARCHAR',
            length: 255,
            nullable: false,
        ),
    ];

    $indexes = [
        new IndexMetadata(
            name: 'idx_title',
            columns: ['title'],
            unique: false,
        ),
    ];

    $properties = [
        'id' => new PropertyMetadata(
            name: 'id',
            columnName: 'id',
            type: 'int',
            isPrimaryKey: true,
            isAutoIncrement: true,
        ),
        'title' => new PropertyMetadata(
            name: 'title',
            columnName: 'title',
            type: 'string',
        ),
    ];

    $metadata = new EntityMetadata(
        entityClass: 'App\Blog\Entity\Post',
        tableName: 'posts',
        primaryKey: 'id',
        properties: $properties,
        columns: $columns,
        indexes: $indexes,
    );

    expect($metadata->entityClass)
        ->toBe('App\Blog\Entity\Post')
        ->and($metadata->tableName)->toBe('posts')
        ->and($metadata->primaryKey)->toBe('id')
        ->and($metadata->columns)->toBe($columns)
        ->and($metadata->indexes)->toBe($indexes)
        ->and($metadata->properties)->toBe($properties);
});

it('creates ColumnMetadata with all properties', function (): void {
    $column = new ColumnMetadata(
        name: 'user_id',
        type: 'INT',
        length: null,
        nullable: true,
        default: 0,
        unique: false,
        primaryKey: false,
        autoIncrement: false,
        references: 'users.id',
        onDelete: 'CASCADE',
        onUpdate: 'CASCADE',
    );

    expect($column->name)
        ->toBe('user_id')
        ->and($column->type)->toBe('INT')
        ->and($column->length)->toBeNull()
        ->and($column->nullable)->toBeTrue()
        ->and($column->default)->toBe(0)
        ->and($column->unique)->toBeFalse()
        ->and($column->primaryKey)->toBeFalse()
        ->and($column->autoIncrement)->toBeFalse()
        ->and($column->references)->toBe('users.id')
        ->and($column->onDelete)->toBe('CASCADE')
        ->and($column->onUpdate)->toBe('CASCADE');
});

it('creates IndexMetadata with all properties', function (): void {
    $index = new IndexMetadata(
        name: 'idx_name_email',
        columns: ['name', 'email'],
        unique: true,
    );

    expect($index->name)
        ->toBe('idx_name_email')
        ->and($index->columns)->toBe(['name', 'email'])
        ->and($index->unique)->toBeTrue();
});

it('creates PropertyMetadata with all properties', function (): void {
    $property = new PropertyMetadata(
        name: 'userId',
        columnName: 'user_id',
        type: 'int',
        nullable: true,
        isPrimaryKey: false,
        isAutoIncrement: false,
        enumClass: null,
        default: 0,
    );

    expect($property->name)
        ->toBe('userId')
        ->and($property->columnName)->toBe('user_id')
        ->and($property->type)->toBe('int')
        ->and($property->nullable)->toBeTrue()
        ->and($property->isPrimaryKey)->toBeFalse()
        ->and($property->isAutoIncrement)->toBeFalse()
        ->and($property->enumClass)->toBeNull()
        ->and($property->default)->toBe(0);
});

it('gets property metadata by name', function (): void {
    $properties = [
        'id' => new PropertyMetadata(
            name: 'id',
            columnName: 'id',
            type: 'int',
            isPrimaryKey: true,
        ),
        'title' => new PropertyMetadata(
            name: 'title',
            columnName: 'title',
            type: 'string',
        ),
    ];

    $metadata = new EntityMetadata(
        entityClass: 'App\Blog\Entity\Post',
        tableName: 'posts',
        primaryKey: 'id',
        properties: $properties,
    );

    expect($metadata->getProperty('id'))
        ->toBe($properties['id'])
        ->and($metadata->getProperty('title'))->toBe($properties['title'])
        ->and($metadata->getProperty('nonexistent'))->toBeNull();
});

it('gets primary key property metadata', function (): void {
    $idProperty = new PropertyMetadata(
        name: 'id',
        columnName: 'id',
        type: 'int',
        isPrimaryKey: true,
    );

    $metadata = new EntityMetadata(
        entityClass: 'App\Blog\Entity\Post',
        tableName: 'posts',
        primaryKey: 'id',
        properties: ['id' => $idProperty],
    );

    expect($metadata->getPrimaryKeyProperty())->toBe($idProperty);
});

it('gets column to property map', function (): void {
    $properties = [
        'userId' => new PropertyMetadata(
            name: 'userId',
            columnName: 'user_id',
            type: 'int',
        ),
        'firstName' => new PropertyMetadata(
            name: 'firstName',
            columnName: 'first_name',
            type: 'string',
        ),
    ];

    $metadata = new EntityMetadata(
        entityClass: 'App\Entity\User',
        tableName: 'users',
        primaryKey: 'userId',
        properties: $properties,
    );

    expect($metadata->getColumnToPropertyMap())->toBe([
        'user_id' => 'userId',
        'first_name' => 'firstName',
    ]);
});

it('gets property to column map', function (): void {
    $properties = [
        'userId' => new PropertyMetadata(
            name: 'userId',
            columnName: 'user_id',
            type: 'int',
        ),
        'firstName' => new PropertyMetadata(
            name: 'firstName',
            columnName: 'first_name',
            type: 'string',
        ),
    ];

    $metadata = new EntityMetadata(
        entityClass: 'App\Entity\User',
        tableName: 'users',
        primaryKey: 'userId',
        properties: $properties,
    );

    expect($metadata->getPropertyToColumnMap())->toBe([
        'userId' => 'user_id',
        'firstName' => 'first_name',
    ]);
});
