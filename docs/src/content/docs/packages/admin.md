---
title: marko/admin
description: Admin contracts and section registry -- defines the structure for admin sections, menu items, and dashboard widgets so any module can contribute to the admin panel.
---

Admin contracts and section registry --- defines the structure for admin sections, menu items, and dashboard widgets so any module can contribute to the admin panel. Modules register admin sections via `#[AdminSection]` attributes, each containing menu items with permission-based visibility. The `AdminSectionRegistry` collects all sections and serves them sorted by priority. This is an interface/contracts package --- install `marko/admin-panel` or `marko/admin-api` for the actual admin UI.

## Installation

```bash
composer require marko/admin
```

## Usage

### Registering an Admin Section

Create a class that implements `AdminSectionInterface` and mark it with `#[AdminSection]`:

```php
use Marko\Admin\Attributes\AdminSection;
use Marko\Admin\Contracts\AdminSectionInterface;
use Marko\Admin\Contracts\MenuItemInterface;
use Marko\Admin\MenuItem;

#[AdminSection(
    id: 'catalog',
    label: 'Catalog',
    icon: 'box',
    sortOrder: 20,
)]
class CatalogSection implements AdminSectionInterface
{
    public function getId(): string
    {
        return 'catalog';
    }

    public function getLabel(): string
    {
        return 'Catalog';
    }

    public function getIcon(): string
    {
        return 'box';
    }

    public function getSortOrder(): int
    {
        return 20;
    }

    /**
     * @return array<MenuItemInterface>
     */
    public function getMenuItems(): array
    {
        return [
            new MenuItem(
                id: 'products',
                label: 'Products',
                url: '/admin/catalog/products',
                icon: 'package',
                sortOrder: 10,
                permission: 'catalog.products.view',
            ),
            new MenuItem(
                id: 'categories',
                label: 'Categories',
                url: '/admin/catalog/categories',
                icon: 'folder',
                sortOrder: 20,
                permission: 'catalog.categories.view',
            ),
        ];
    }
}
```

### Declaring Permissions

Use `#[AdminPermission]` to declare permissions that your section requires. The attribute is repeatable, so you can stack multiple permissions on a single class:

```php
use Marko\Admin\Attributes\AdminPermission;
use Marko\Admin\Attributes\AdminSection;
use Marko\Admin\Contracts\AdminSectionInterface;

#[AdminSection(id: 'catalog', label: 'Catalog')]
#[AdminPermission(id: 'catalog.products.view', label: 'View Products')]
#[AdminPermission(id: 'catalog.products.edit', label: 'Edit Products')]
class CatalogSection implements AdminSectionInterface
{
    // ...
}
```

### Querying Sections

Inject `AdminSectionRegistryInterface` to access all registered sections:

```php
use Marko\Admin\Contracts\AdminSectionInterface;
use Marko\Admin\Contracts\AdminSectionRegistryInterface;

class NavigationBuilder
{
    public function __construct(
        private readonly AdminSectionRegistryInterface $adminSectionRegistry,
    ) {}

    public function buildMenu(): array
    {
        $sections = $this->adminSectionRegistry->all(); // sorted by sortOrder

        return array_map(
            fn (AdminSectionInterface $section) => [
                'label' => $section->getLabel(),
                'items' => $section->getMenuItems(),
            ],
            $sections,
        );
    }
}
```

### Creating Dashboard Widgets

Implement `DashboardWidgetInterface` to add widgets to the admin dashboard:

```php
use Marko\Admin\Contracts\DashboardWidgetInterface;

class RecentOrdersWidget implements DashboardWidgetInterface
{
    public function getId(): string
    {
        return 'recent-orders';
    }

    public function getLabel(): string
    {
        return 'Recent Orders';
    }

    public function getSortOrder(): int
    {
        return 10;
    }

    public function render(): string
    {
        return '<div>Order list here</div>';
    }
}
```

### Admin Configuration

The `AdminConfigInterface` provides access to admin panel settings such as the route prefix and display name. It reads from [marko/config](/docs/packages/config/) under the `admin` namespace:

```php title="config/admin.php"
return [
    'route_prefix' => '/admin',
    'name' => 'My Admin Panel',
];
```

```php
use Marko\Admin\Config\AdminConfigInterface;

class AdminRouter
{
    public function __construct(
        private readonly AdminConfigInterface $adminConfig,
    ) {}

    public function getBaseUrl(): string
    {
        return $this->adminConfig->getRoutePrefix(); // e.g. '/admin'
    }

    public function getPanelName(): string
    {
        return $this->adminConfig->getName(); // e.g. 'My Admin Panel'
    }
}
```

The route prefix is validated on access --- it must start with `/` or an `InvalidAdminConfigException` is thrown.

## API Reference

### AdminSectionInterface

```php
interface AdminSectionInterface
{
    public function getId(): string;
    public function getLabel(): string;
    public function getIcon(): string;
    public function getSortOrder(): int;
    public function getMenuItems(): array;
}
```

### AdminSectionRegistryInterface

```php
interface AdminSectionRegistryInterface
{
    public function register(AdminSectionInterface $section): void;
    public function all(): array;
    public function get(string $id): AdminSectionInterface;
}
```

Calling `register()` with a duplicate section id throws `AdminException`. Calling `get()` with an unknown id also throws `AdminException`.

### MenuItemInterface

```php
interface MenuItemInterface
{
    public function getId(): string;
    public function getLabel(): string;
    public function getUrl(): string;
    public function getIcon(): string;
    public function getSortOrder(): int;
    public function getPermission(): string;
}
```

A concrete `MenuItem` class is provided with all properties accepted as constructor parameters. The `icon`, `sortOrder`, and `permission` parameters are optional (defaulting to `''`, `0`, and `''` respectively).

### DashboardWidgetInterface

```php
interface DashboardWidgetInterface
{
    public function getId(): string;
    public function getLabel(): string;
    public function getSortOrder(): int;
    public function render(): string;
}
```

### AdminConfigInterface

```php
interface AdminConfigInterface
{
    public function getRoutePrefix(): string;
    public function getName(): string;
}
```

### Attributes

| Attribute | Target | Parameters |
|-----------|--------|------------|
| `#[AdminSection]` | Class | `id`, `label`, `icon` (optional), `sortOrder` (optional) |
| `#[AdminPermission]` | Class (repeatable) | `id`, `label` (optional) |
