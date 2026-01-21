<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Schema;

use Marko\Database\Schema\ForeignKey;
use ReflectionClass;

describe('ForeignKey', function (): void {
    it('creates readonly ForeignKey class with columns, references, and actions', function (): void {
        $foreignKey = new ForeignKey(
            name: 'fk_posts_author',
            columns: ['author_id'],
            referencedTable: 'users',
            referencedColumns: ['id'],
            onDelete: 'CASCADE',
            onUpdate: 'SET NULL',
        );

        expect($foreignKey->name)->toBe('fk_posts_author')
            ->and($foreignKey->columns)->toBe(['author_id'])
            ->and($foreignKey->referencedTable)->toBe('users')
            ->and($foreignKey->referencedColumns)->toBe(['id'])
            ->and($foreignKey->onDelete)->toBe('CASCADE')
            ->and($foreignKey->onUpdate)->toBe('SET NULL');

        // Composite foreign key
        $compositeFk = new ForeignKey(
            name: 'fk_order_items_product',
            columns: ['product_id', 'variant_id'],
            referencedTable: 'product_variants',
            referencedColumns: ['product_id', 'variant_id'],
        );

        expect($compositeFk->columns)->toBe(['product_id', 'variant_id'])
            ->and($compositeFk->referencedColumns)->toBe(['product_id', 'variant_id'])
            ->and($compositeFk->onDelete)->toBeNull()
            ->and($compositeFk->onUpdate)->toBeNull();

        // Verify it's a readonly class
        $reflection = new ReflectionClass($foreignKey);
        expect($reflection->isReadOnly())->toBeTrue();
    });
});
