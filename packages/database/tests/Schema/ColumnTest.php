<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Schema;

use Marko\Database\Schema\Column;
use ReflectionClass;

describe('Column', function (): void {
    it('creates readonly Column class with name, type, and constraints', function (): void {
        $column = new Column(
            name: 'email',
            type: 'varchar',
            length: 255,
            nullable: true,
            default: null,
        );

        expect($column->name)->toBe('email')
            ->and($column->type)->toBe('varchar')
            ->and($column->length)->toBe(255)
            ->and($column->nullable)->toBeTrue()
            ->and($column->default)->toBeNull();

        // Verify it's a readonly class
        $reflection = new ReflectionClass($column);
        expect($reflection->isReadOnly())->toBeTrue();
    });

    it('supports column properties: nullable, default, unique, primaryKey, autoIncrement', function (): void {
        $idColumn = new Column(
            name: 'id',
            type: 'int',
            primaryKey: true,
            autoIncrement: true,
        );

        expect($idColumn->primaryKey)->toBeTrue()
            ->and($idColumn->autoIncrement)->toBeTrue()
            ->and($idColumn->unique)->toBeFalse()
            ->and($idColumn->nullable)->toBeFalse();

        $slugColumn = new Column(
            name: 'slug',
            type: 'varchar',
            length: 255,
            unique: true,
        );

        expect($slugColumn->unique)->toBeTrue()
            ->and($slugColumn->primaryKey)->toBeFalse();

        $statusColumn = new Column(
            name: 'status',
            type: 'varchar',
            default: 'draft',
        );

        expect($statusColumn->default)->toBe('draft');

        $createdColumn = new Column(
            name: 'created_at',
            type: 'timestamp',
            nullable: true,
            default: null,
        );

        expect($createdColumn->nullable)->toBeTrue()
            ->and($createdColumn->default)->toBeNull();
    });

    it('supports column foreign key reference with onDelete/onUpdate', function (): void {
        $column = new Column(
            name: 'author_id',
            type: 'int',
            references: 'users.id',
            onDelete: 'CASCADE',
            onUpdate: 'CASCADE',
        );

        expect($column->references)->toBe('users.id')
            ->and($column->onDelete)->toBe('CASCADE')
            ->and($column->onUpdate)->toBe('CASCADE');

        // Column without foreign key reference
        $simpleColumn = new Column(
            name: 'title',
            type: 'varchar',
        );

        expect($simpleColumn->references)->toBeNull()
            ->and($simpleColumn->onDelete)->toBeNull()
            ->and($simpleColumn->onUpdate)->toBeNull();

        // Column with reference and default actions
        $columnWithDefaults = new Column(
            name: 'category_id',
            type: 'int',
            references: 'categories.id',
        );

        expect($columnWithDefaults->references)->toBe('categories.id')
            ->and($columnWithDefaults->onDelete)->toBeNull()
            ->and($columnWithDefaults->onUpdate)->toBeNull();
    });

    it('provides Column::withConstraint() style methods', function (): void {
        $column = new Column(name: 'id', type: 'int');

        // Original column is unchanged
        $columnWithPk = $column->withPrimaryKey();
        expect($column->primaryKey)->toBeFalse()
            ->and($columnWithPk->primaryKey)->toBeTrue()
            ->and($columnWithPk->name)->toBe('id')
            ->and($columnWithPk->type)->toBe('int');

        // Chain multiple constraints
        $columnFull = $column
            ->withPrimaryKey()
            ->withAutoIncrement();
        expect($columnFull->primaryKey)->toBeTrue()
            ->and($columnFull->autoIncrement)->toBeTrue();

        // withNullable
        $emailColumn = new Column(name: 'email', type: 'varchar');
        $nullableEmail = $emailColumn->withNullable();
        expect($emailColumn->nullable)->toBeFalse()
            ->and($nullableEmail->nullable)->toBeTrue();

        // withUnique
        $slugColumn = new Column(name: 'slug', type: 'varchar');
        $uniqueSlug = $slugColumn->withUnique();
        expect($slugColumn->unique)->toBeFalse()
            ->and($uniqueSlug->unique)->toBeTrue();

        // withDefault
        $statusColumn = new Column(name: 'status', type: 'varchar');
        $statusWithDefault = $statusColumn->withDefault('draft');
        expect($statusColumn->default)->toBeNull()
            ->and($statusWithDefault->default)->toBe('draft');

        // withReference
        $authorColumn = new Column(name: 'author_id', type: 'int');
        $authorWithRef = $authorColumn->withReference('users.id', 'CASCADE', 'SET NULL');
        expect($authorColumn->references)->toBeNull()
            ->and($authorWithRef->references)->toBe('users.id')
            ->and($authorWithRef->onDelete)->toBe('CASCADE')
            ->and($authorWithRef->onUpdate)->toBe('SET NULL');
    });

    it('treats type comparison as case-insensitive', function (): void {
        $upper = new Column(name: 'title', type: 'VARCHAR', length: 255);
        $lower = new Column(name: 'title', type: 'varchar', length: 255);

        expect($upper->equals($lower))->toBeTrue();
    });

    it('treats enum as equivalent to varchar via type aliases', function (): void {
        $enum = new Column(name: 'status', type: 'enum', length: 20);
        $varchar = new Column(name: 'status', type: 'varchar', length: 20);

        expect($enum->equals($varchar))->toBeTrue();
    });

    it('treats datetime as equivalent to timestamp via type aliases', function (): void {
        $datetime = new Column(name: 'created_at', type: 'datetime');
        $timestamp = new Column(name: 'created_at', type: 'timestamp');

        expect($datetime->equals($timestamp))->toBeTrue();
    });

    it('treats TEXT length comparison as case-insensitive', function (): void {
        $text1 = new Column(name: 'content', type: 'text', length: null);
        $text2 = new Column(name: 'content', type: 'TEXT', length: 65535);

        // TEXT type ignores length differences
        expect($text1->equals($text2))->toBeTrue();
    });
});
