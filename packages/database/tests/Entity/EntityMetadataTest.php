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

    expect($metadata->entityClass)->toBe('App\Blog\Entity\Post');
    expect($metadata->tableName)->toBe('posts');
    expect($metadata->primaryKey)->toBe('id');
    expect($metadata->columns)->toBe($columns);
    expect($metadata->indexes)->toBe($indexes);
    expect($metadata->properties)->toBe($properties);
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

    expect($column->name)->toBe('user_id');
    expect($column->type)->toBe('INT');
    expect($column->length)->toBeNull();
    expect($column->nullable)->toBeTrue();
    expect($column->default)->toBe(0);
    expect($column->unique)->toBeFalse();
    expect($column->primaryKey)->toBeFalse();
    expect($column->autoIncrement)->toBeFalse();
    expect($column->references)->toBe('users.id');
    expect($column->onDelete)->toBe('CASCADE');
    expect($column->onUpdate)->toBe('CASCADE');
});

it('creates IndexMetadata with all properties', function (): void {
    $index = new IndexMetadata(
        name: 'idx_name_email',
        columns: ['name', 'email'],
        unique: true,
    );

    expect($index->name)->toBe('idx_name_email');
    expect($index->columns)->toBe(['name', 'email']);
    expect($index->unique)->toBeTrue();
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

    expect($property->name)->toBe('userId');
    expect($property->columnName)->toBe('user_id');
    expect($property->type)->toBe('int');
    expect($property->nullable)->toBeTrue();
    expect($property->isPrimaryKey)->toBeFalse();
    expect($property->isAutoIncrement)->toBeFalse();
    expect($property->enumClass)->toBeNull();
    expect($property->default)->toBe(0);
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
        properties: $properties,
    );

    expect($metadata->getProperty('id'))->toBe($properties['id']);
    expect($metadata->getProperty('title'))->toBe($properties['title']);
    expect($metadata->getProperty('nonexistent'))->toBeNull();
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
        properties: $properties,
    );

    expect($metadata->getPropertyToColumnMap())->toBe([
        'userId' => 'user_id',
        'firstName' => 'first_name',
    ]);
});
