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
    public int $orderId;

    public int $productId;

    public int $quantity;
}

it('creates #[ForeignKey] attribute for composite foreign keys', function (): void {
    $reflection = new ReflectionClass(CompositeForeignKeyTestEntity::class);
    $attributes = $reflection->getAttributes(ForeignKey::class);

    expect($attributes)->toHaveCount(1);

    $fk = $attributes[0]->newInstance();
    expect($fk)->toBeInstanceOf(ForeignKey::class);
    expect($fk->name)->toBe('fk_order_product');
    expect($fk->columns)->toBe(['order_id', 'product_id']);
    expect($fk->references)->toBe('order_products');
    expect($fk->referencedColumns)->toBe(['order_id', 'product_id']);
    expect($fk->onDelete)->toBe('CASCADE');
    expect($fk->onUpdate)->toBe('CASCADE');
});
