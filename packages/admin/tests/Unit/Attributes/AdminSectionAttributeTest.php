<?php

declare(strict_types=1);

use Marko\Admin\Attributes\AdminSection;

#[AdminSection(id: 'catalog', label: 'Catalog', icon: 'box', sortOrder: 10)]
class CatalogSection {}

#[AdminSection(id: 'sales', label: 'Sales')]
class SalesSection {}

it('creates AdminSection attribute targeting classes with id, label, icon, sortOrder properties', function (): void {
    $reflection = new ReflectionClass(AdminSection::class);

    // Verify it targets classes
    $attributeAttribute = $reflection->getAttributes(Attribute::class)[0]->newInstance();
    expect($attributeAttribute->flags)->toBe(Attribute::TARGET_CLASS)
        ->and($reflection->isReadOnly())->toBeTrue();

    // Verify it's readonly

    // Verify full attribute instantiation
    $catalogReflection = new ReflectionClass(CatalogSection::class);
    $catalogAttrs = $catalogReflection->getAttributes(AdminSection::class);

    expect($catalogAttrs)->toHaveCount(1);

    $catalog = $catalogAttrs[0]->newInstance();

    expect($catalog->id)->toBe('catalog')
        ->and($catalog->label)->toBe('Catalog')
        ->and($catalog->icon)->toBe('box')
        ->and($catalog->sortOrder)->toBe(10);

    // Verify defaults
    $salesReflection = new ReflectionClass(SalesSection::class);
    $sales = $salesReflection->getAttributes(AdminSection::class)[0]->newInstance();

    expect($sales->id)->toBe('sales')
        ->and($sales->label)->toBe('Sales')
        ->and($sales->icon)->toBe('')
        ->and($sales->sortOrder)->toBe(0);
});
