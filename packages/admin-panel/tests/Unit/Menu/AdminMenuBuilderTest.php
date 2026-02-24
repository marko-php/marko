<?php

declare(strict_types=1);

namespace Marko\AdminPanel\Tests\Unit\Menu;

use Marko\Admin\Contracts\AdminSectionInterface;
use Marko\Admin\Contracts\AdminSectionRegistryInterface;
use Marko\Admin\Contracts\MenuItemInterface;
use Marko\Admin\MenuItem;
use Marko\AdminAuth\Entity\AdminUserInterface;
use Marko\AdminPanel\Menu\AdminMenuBuilder;

// Stub for AdminSectionRegistryInterface
class StubMenuSectionRegistry implements AdminSectionRegistryInterface
{
    /** @var array<string, AdminSectionInterface> */
    private array $sections = [];

    public function register(
        AdminSectionInterface $section,
    ): void {
        $this->sections[$section->getId()] = $section;
    }

    public function all(): array
    {
        $sections = array_values($this->sections);

        usort(
            $sections,
            fn (AdminSectionInterface $a, AdminSectionInterface $b): int => $a->getSortOrder() <=> $b->getSortOrder(),
        );

        return $sections;
    }

    public function get(
        string $id,
    ): AdminSectionInterface {
        return $this->sections[$id];
    }
}

// Stub for AdminSectionInterface with menu items
class StubMenuSection implements AdminSectionInterface
{
    /**
     * @param array<MenuItemInterface> $menuItems
     */
    public function __construct(
        private readonly string $id,
        private readonly string $label,
        private readonly string $icon = 'default',
        private readonly int $sortOrder = 0,
        private array $menuItems = [],
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function getMenuItems(): array
    {
        return $this->menuItems;
    }
}

// Stub for AdminUserInterface
class StubMenuAdminUser implements AdminUserInterface
{
    /**
     * @param array<string> $permissionKeys
     */
    public function __construct(
        private readonly int $id = 1,
        private array $permissionKeys = [],
        private bool $isSuperAdmin = false,
    ) {}

    public function getEmail(): string
    {
        return 'admin@example.com';
    }

    public function getName(): string
    {
        return 'Admin';
    }

    public function getAuthIdentifier(): int|string
    {
        return $this->id;
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthPassword(): string
    {
        return 'hashed';
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken(
        ?string $token,
    ): void {}

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }

    public function setRoles(
        array $roles,
        array $permissionKeys = [],
    ): void {}

    public function getRoles(): array
    {
        return [];
    }

    public function getPermissionKeys(): array
    {
        return $this->permissionKeys;
    }

    public function hasPermission(
        string $key,
    ): bool {
        if ($this->isSuperAdmin) {
            return true;
        }

        return in_array($key, $this->permissionKeys, true);
    }

    public function hasRole(
        string $slug,
    ): bool {
        return false;
    }
}

it('builds menu from registered admin sections', function (): void {
    $registry = new StubMenuSectionRegistry();

    $registry->register(new StubMenuSection(
        id: 'catalog',
        label: 'Catalog',
        icon: 'box',
        sortOrder: 10,
        menuItems: [
            new MenuItem(
                id: 'products',
                label: 'Products',
                url: '/admin/catalog/products',
                permission: 'catalog.products.view',
            ),
            new MenuItem(
                id: 'categories',
                label: 'Categories',
                url: '/admin/catalog/categories',
                permission: 'catalog.categories.view',
            ),
        ],
    ));

    $registry->register(new StubMenuSection(
        id: 'content',
        label: 'Content',
        icon: 'file-text',
        sortOrder: 20,
        menuItems: [
            new MenuItem(id: 'pages', label: 'Pages', url: '/admin/content/pages', permission: 'content.pages.view'),
        ],
    ));

    $user = new StubMenuAdminUser(
        permissionKeys: ['catalog.products.view', 'catalog.categories.view', 'content.pages.view'],
    );

    $builder = new AdminMenuBuilder($registry);
    $menu = $builder->build($user, '/admin');

    expect($menu)->toBeArray()
        ->and($menu)->toHaveCount(2)
        ->and($menu[0]['section']->getId())->toBe('catalog')
        ->and($menu[0]['items'])->toHaveCount(2)
        ->and($menu[0]['items'][0]->getId())->toBe('products')
        ->and($menu[0]['items'][1]->getId())->toBe('categories')
        ->and($menu[1]['section']->getId())->toBe('content')
        ->and($menu[1]['items'])->toHaveCount(1);
});

it('sorts sections by sortOrder', function (): void {
    $registry = new StubMenuSectionRegistry();

    // Register in reverse order to prove sorting works
    $registry->register(new StubMenuSection(
        id: 'settings',
        label: 'Settings',
        sortOrder: 100,
        menuItems: [
            new MenuItem(
                id: 'general',
                label: 'General',
                url: '/admin/settings/general',
                permission: 'settings.general.view',
            ),
        ],
    ));

    $registry->register(new StubMenuSection(
        id: 'catalog',
        label: 'Catalog',
        sortOrder: 10,
        menuItems: [
            new MenuItem(
                id: 'products',
                label: 'Products',
                url: '/admin/catalog/products',
                permission: 'catalog.products.view',
            ),
        ],
    ));

    $registry->register(new StubMenuSection(
        id: 'content',
        label: 'Content',
        sortOrder: 50,
        menuItems: [
            new MenuItem(id: 'pages', label: 'Pages', url: '/admin/content/pages', permission: 'content.pages.view'),
        ],
    ));

    $user = new StubMenuAdminUser(
        permissionKeys: ['settings.general.view', 'catalog.products.view', 'content.pages.view'],
    );

    $builder = new AdminMenuBuilder($registry);
    $menu = $builder->build($user, '/admin');

    expect($menu)->toHaveCount(3)
        ->and($menu[0]['section']->getId())->toBe('catalog')
        ->and($menu[0]['section']->getSortOrder())->toBe(10)
        ->and($menu[1]['section']->getId())->toBe('content')
        ->and($menu[1]['section']->getSortOrder())->toBe(50)
        ->and($menu[2]['section']->getId())->toBe('settings')
        ->and($menu[2]['section']->getSortOrder())->toBe(100);
});

it('sorts menu items within each section by sortOrder', function (): void {
    $registry = new StubMenuSectionRegistry();

    $registry->register(new StubMenuSection(
        id: 'catalog',
        label: 'Catalog',
        sortOrder: 10,
        menuItems: [
            new MenuItem(
                id: 'inventory',
                label: 'Inventory',
                url: '/admin/catalog/inventory',
                sortOrder: 30,
                permission: 'catalog.inventory.view',
            ),
            new MenuItem(
                id: 'products',
                label: 'Products',
                url: '/admin/catalog/products',
                sortOrder: 10,
                permission: 'catalog.products.view',
            ),
            new MenuItem(
                id: 'categories',
                label: 'Categories',
                url: '/admin/catalog/categories',
                sortOrder: 20,
                permission: 'catalog.categories.view',
            ),
        ],
    ));

    $user = new StubMenuAdminUser(
        permissionKeys: ['catalog.inventory.view', 'catalog.products.view', 'catalog.categories.view'],
    );

    $builder = new AdminMenuBuilder($registry);
    $menu = $builder->build($user, '/admin');

    expect($menu)->toHaveCount(1)
        ->and($menu[0]['items'])->toHaveCount(3)
        ->and($menu[0]['items'][0]->getId())->toBe('products')
        ->and($menu[0]['items'][0]->getSortOrder())->toBe(10)
        ->and($menu[0]['items'][1]->getId())->toBe('categories')
        ->and($menu[0]['items'][1]->getSortOrder())->toBe(20)
        ->and($menu[0]['items'][2]->getId())->toBe('inventory')
        ->and($menu[0]['items'][2]->getSortOrder())->toBe(30);
});

it('filters out menu items the user lacks permission for', function (): void {
    $registry = new StubMenuSectionRegistry();

    $registry->register(new StubMenuSection(
        id: 'catalog',
        label: 'Catalog',
        sortOrder: 10,
        menuItems: [
            new MenuItem(
                id: 'products',
                label: 'Products',
                url: '/admin/catalog/products',
                permission: 'catalog.products.view',
            ),
            new MenuItem(
                id: 'categories',
                label: 'Categories',
                url: '/admin/catalog/categories',
                permission: 'catalog.categories.view',
            ),
            new MenuItem(
                id: 'inventory',
                label: 'Inventory',
                url: '/admin/catalog/inventory',
                permission: 'catalog.inventory.view',
            ),
        ],
    ));

    $registry->register(new StubMenuSection(
        id: 'content',
        label: 'Content',
        sortOrder: 20,
        menuItems: [
            new MenuItem(id: 'pages', label: 'Pages', url: '/admin/content/pages', permission: 'content.pages.view'),
            new MenuItem(
                id: 'blocks',
                label: 'Blocks',
                url: '/admin/content/blocks',
                permission: 'content.blocks.view',
            ),
        ],
    ));

    // User only has permission for products and pages
    $user = new StubMenuAdminUser(
        permissionKeys: ['catalog.products.view', 'content.pages.view'],
    );

    $builder = new AdminMenuBuilder($registry);
    $menu = $builder->build($user, '/admin');

    expect($menu)->toHaveCount(2)
        ->and($menu[0]['section']->getId())->toBe('catalog')
        ->and($menu[0]['items'])->toHaveCount(1)
        ->and($menu[0]['items'][0]->getId())->toBe('products')
        ->and($menu[1]['section']->getId())->toBe('content')
        ->and($menu[1]['items'])->toHaveCount(1)
        ->and($menu[1]['items'][0]->getId())->toBe('pages');
});

it('excludes sections where user has no permitted items', function (): void {
    $registry = new StubMenuSectionRegistry();

    $registry->register(new StubMenuSection(
        id: 'catalog',
        label: 'Catalog',
        sortOrder: 10,
        menuItems: [
            new MenuItem(
                id: 'products',
                label: 'Products',
                url: '/admin/catalog/products',
                permission: 'catalog.products.view',
            ),
        ],
    ));

    $registry->register(new StubMenuSection(
        id: 'settings',
        label: 'Settings',
        sortOrder: 20,
        menuItems: [
            new MenuItem(
                id: 'general',
                label: 'General',
                url: '/admin/settings/general',
                permission: 'settings.general.view',
            ),
        ],
    ));

    // User only has catalog permission, not settings
    $user = new StubMenuAdminUser(
        permissionKeys: ['catalog.products.view'],
    );

    $builder = new AdminMenuBuilder($registry);
    $menu = $builder->build($user, '/admin');

    expect($menu)->toHaveCount(1)
        ->and($menu[0]['section']->getId())->toBe('catalog');
});

it('shows all menu items for super admin users', function (): void {
    $registry = new StubMenuSectionRegistry();

    $registry->register(new StubMenuSection(
        id: 'catalog',
        label: 'Catalog',
        sortOrder: 10,
        menuItems: [
            new MenuItem(
                id: 'products',
                label: 'Products',
                url: '/admin/catalog/products',
                permission: 'catalog.products.view',
            ),
            new MenuItem(
                id: 'categories',
                label: 'Categories',
                url: '/admin/catalog/categories',
                permission: 'catalog.categories.view',
            ),
        ],
    ));

    $registry->register(new StubMenuSection(
        id: 'settings',
        label: 'Settings',
        sortOrder: 20,
        menuItems: [
            new MenuItem(
                id: 'general',
                label: 'General',
                url: '/admin/settings/general',
                permission: 'settings.general.view',
            ),
            new MenuItem(
                id: 'advanced',
                label: 'Advanced',
                url: '/admin/settings/advanced',
                permission: 'settings.advanced.view',
            ),
        ],
    ));

    // Super admin has no explicit permissions but isSuperAdmin returns true for all
    $user = new StubMenuAdminUser(
        permissionKeys: [],
        isSuperAdmin: true,
    );

    $builder = new AdminMenuBuilder($registry);
    $menu = $builder->build($user, '/admin');

    expect($menu)->toHaveCount(2)
        ->and($menu[0]['section']->getId())->toBe('catalog')
        ->and($menu[0]['items'])->toHaveCount(2)
        ->and($menu[1]['section']->getId())->toBe('settings')
        ->and($menu[1]['items'])->toHaveCount(2);
});

it('marks the active menu item based on current request path', function (): void {
    $registry = new StubMenuSectionRegistry();

    $registry->register(new StubMenuSection(
        id: 'catalog',
        label: 'Catalog',
        sortOrder: 10,
        menuItems: [
            new MenuItem(
                id: 'products',
                label: 'Products',
                url: '/admin/catalog/products',
                permission: 'catalog.products.view',
            ),
            new MenuItem(
                id: 'categories',
                label: 'Categories',
                url: '/admin/catalog/categories',
                permission: 'catalog.categories.view',
            ),
        ],
    ));

    $registry->register(new StubMenuSection(
        id: 'content',
        label: 'Content',
        sortOrder: 20,
        menuItems: [
            new MenuItem(id: 'pages', label: 'Pages', url: '/admin/content/pages', permission: 'content.pages.view'),
        ],
    ));

    $user = new StubMenuAdminUser(
        permissionKeys: ['catalog.products.view', 'catalog.categories.view', 'content.pages.view'],
    );

    $builder = new AdminMenuBuilder($registry);

    // Request path matches the categories item
    $menu = $builder->build($user, '/admin/catalog/categories');

    expect($menu[0]['active'])->toBeTrue()
        ->and($menu[0]['activeItemId'])->toBe('categories')
        ->and($menu[1]['active'])->toBeFalse()
        ->and($menu[1]['activeItemId'])->toBeNull();
});

it('marks no active item when path does not match any menu item', function (): void {
    $registry = new StubMenuSectionRegistry();

    $registry->register(new StubMenuSection(
        id: 'catalog',
        label: 'Catalog',
        sortOrder: 10,
        menuItems: [
            new MenuItem(
                id: 'products',
                label: 'Products',
                url: '/admin/catalog/products',
                permission: 'catalog.products.view',
            ),
        ],
    ));

    $user = new StubMenuAdminUser(
        permissionKeys: ['catalog.products.view'],
    );

    $builder = new AdminMenuBuilder($registry);
    $menu = $builder->build($user, '/admin/unknown/path');

    expect($menu[0]['active'])->toBeFalse()
        ->and($menu[0]['activeItemId'])->toBeNull();
});

it('returns empty menu when no sections are registered', function (): void {
    $registry = new StubMenuSectionRegistry();

    $user = new StubMenuAdminUser(
        permissionKeys: ['some.permission'],
    );

    $builder = new AdminMenuBuilder($registry);
    $menu = $builder->build($user, '/admin');

    expect($menu)->toBeArray()
        ->and($menu)->toBeEmpty();
});

it('builds dashboard section list filtered by user permissions', function (): void {
    $registry = new StubMenuSectionRegistry();

    $registry->register(new StubMenuSection(
        id: 'catalog',
        label: 'Catalog',
        icon: 'box',
        sortOrder: 10,
        menuItems: [
            new MenuItem(
                id: 'products',
                label: 'Products',
                url: '/admin/catalog/products',
                permission: 'catalog.products.view',
            ),
            new MenuItem(
                id: 'categories',
                label: 'Categories',
                url: '/admin/catalog/categories',
                permission: 'catalog.categories.view',
            ),
        ],
    ));

    $registry->register(new StubMenuSection(
        id: 'content',
        label: 'Content',
        icon: 'file-text',
        sortOrder: 20,
        menuItems: [
            new MenuItem(id: 'pages', label: 'Pages', url: '/admin/content/pages', permission: 'content.pages.view'),
        ],
    ));

    $registry->register(new StubMenuSection(
        id: 'settings',
        label: 'Settings',
        icon: 'gear',
        sortOrder: 30,
        menuItems: [
            new MenuItem(
                id: 'general',
                label: 'General',
                url: '/admin/settings/general',
                permission: 'settings.general.view',
            ),
        ],
    ));

    // User has catalog and content permissions but not settings
    $user = new StubMenuAdminUser(
        permissionKeys: ['catalog.products.view', 'content.pages.view'],
    );

    $builder = new AdminMenuBuilder($registry);
    $sections = $builder->buildDashboardSections($user);

    expect($sections)->toHaveCount(2)
        ->and($sections[0]->getId())->toBe('catalog')
        ->and($sections[1]->getId())->toBe('content');
});

it('returns empty dashboard sections when user has no permissions', function (): void {
    $registry = new StubMenuSectionRegistry();

    $registry->register(new StubMenuSection(
        id: 'catalog',
        label: 'Catalog',
        sortOrder: 10,
        menuItems: [
            new MenuItem(
                id: 'products',
                label: 'Products',
                url: '/admin/catalog/products',
                permission: 'catalog.products.view',
            ),
        ],
    ));

    $user = new StubMenuAdminUser(
        permissionKeys: [],
    );

    $builder = new AdminMenuBuilder($registry);
    $sections = $builder->buildDashboardSections($user);

    expect($sections)->toBeEmpty();
});

it('includes items with empty permission string for all users', function (): void {
    $registry = new StubMenuSectionRegistry();

    $registry->register(new StubMenuSection(
        id: 'dashboard',
        label: 'Dashboard',
        sortOrder: 0,
        menuItems: [
            new MenuItem(id: 'home', label: 'Home', url: '/admin', permission: ''),
        ],
    ));

    $user = new StubMenuAdminUser(
        permissionKeys: [],
    );

    $builder = new AdminMenuBuilder($registry);
    $menu = $builder->build($user, '/admin');

    expect($menu)->toHaveCount(1)
        ->and($menu[0]['items'])->toHaveCount(1)
        ->and($menu[0]['items'][0]->getId())->toBe('home');
});
