<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Attributes;

use Marko\Database\Attributes\ForeignKey;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use ReflectionClass;

#[Table('order_items')]
#[ForeignKey(
    name: 'fk_order_product',
    columns: ['order_id', 'product_id'],
    references: 'order_products',
    referencedColumns: ['order_id', 'product_id'],
    onDelete: 'CASCADE',
    onUpdate: 'CASCADE',
)]
class CompositeForeignKeyTestEntity extends Entity
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    public int $orderId;

    /** @noinspection PhpUnused - Entity property for structural definition */
    public int $productId;

    /** @noinspection PhpUnused - Entity property for structural definition */
    public int $quantity;
}

it('creates #[ForeignKey] attribute for composite foreign keys', function (): void {
    $reflection = new ReflectionClass(CompositeForeignKeyTestEntity::class);
    $attributes = $reflection->getAttributes(ForeignKey::class);
    $fk = $attributes[0]->newInstance();

    expect($attributes)->toHaveCount(1)
        ->and($fk)->toBeInstanceOf(ForeignKey::class)
        ->and($fk->name)->toBe('fk_order_product')
        ->and($fk->columns)->toBe(['order_id', 'product_id'])
        ->and($fk->references)->toBe('order_products')
        ->and($fk->referencedColumns)->toBe(['order_id', 'product_id'])
        ->and($fk->onDelete)->toBe('CASCADE')
        ->and($fk->onUpdate)->toBe('CASCADE');
});
