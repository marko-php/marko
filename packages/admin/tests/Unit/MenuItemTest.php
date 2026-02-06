<?php

declare(strict_types=1);

use Marko\Admin\Contracts\MenuItemInterface;
use Marko\Admin\MenuItem;

it('creates MenuItem value object implementing MenuItemInterface', function (): void {
    $item = new MenuItem(
        id: 'products',
        label: 'Products',
        url: '/admin/products',
        icon: 'package',
        sortOrder: 10,
        permission: 'catalog:view',
    );

    expect($item)->toBeInstanceOf(MenuItemInterface::class)
        ->and($item->getId())->toBe('products')
        ->and($item->getLabel())->toBe('Products')
        ->and($item->getUrl())->toBe('/admin/products')
        ->and($item->getIcon())->toBe('package')
        ->and($item->getSortOrder())->toBe(10)
        ->and($item->getPermission())->toBe('catalog:view');

    // Verify defaults
    $simple = new MenuItem(
        id: 'dashboard',
        label: 'Dashboard',
        url: '/admin/dashboard',
    );

    expect($simple->getIcon())->toBe('')
        ->and($simple->getSortOrder())->toBe(0)
        ->and($simple->getPermission())->toBe('');

    // Verify readonly
    $reflection = new ReflectionClass(MenuItem::class);
    expect($reflection->isReadOnly())->toBeTrue();
});
