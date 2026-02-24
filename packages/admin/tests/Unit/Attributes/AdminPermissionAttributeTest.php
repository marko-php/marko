<?php

declare(strict_types=1);

use Marko\Admin\Attributes\AdminPermission;

#[AdminPermission(id: 'catalog:view', label: 'View Catalog')]
#[AdminPermission(id: 'catalog:edit', label: 'Edit Catalog')]
class CatalogController {}

#[AdminPermission(id: 'sales:view')]
class SalesController {}

it('creates AdminPermission attribute targeting classes with repeatable support for id and label', function (): void {
    $reflection = new ReflectionClass(AdminPermission::class);

    // Verify it targets classes and is repeatable
    $attributeAttribute = $reflection->getAttributes(Attribute::class)[0]->newInstance();
    expect($attributeAttribute->flags)->toBe(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)
        ->and($reflection->isReadOnly())->toBeTrue();

    // Verify it's readonly

    // Verify repeatable usage
    $catalogReflection = new ReflectionClass(CatalogController::class);
    $permissions = $catalogReflection->getAttributes(AdminPermission::class);

    expect($permissions)->toHaveCount(2);

    $first = $permissions[0]->newInstance();
    expect($first->id)->toBe('catalog:view')
        ->and($first->label)->toBe('View Catalog');

    $second = $permissions[1]->newInstance();
    expect($second->id)->toBe('catalog:edit')
        ->and($second->label)->toBe('Edit Catalog');

    // Verify label defaults to empty string
    $salesReflection = new ReflectionClass(SalesController::class);
    $salesPerms = $salesReflection->getAttributes(AdminPermission::class);

    expect($salesPerms)->toHaveCount(1);

    $salesPerm = $salesPerms[0]->newInstance();
    expect($salesPerm->id)->toBe('sales:view')
        ->and($salesPerm->label)->toBe('');
});
