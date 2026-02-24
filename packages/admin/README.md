# Marko Admin

Admin contracts and section registry--defines the structure for admin sections, menu items, and dashboard widgets so any module can contribute to the admin panel.

## Overview

Admin provides the interfaces and discovery system for building a modular admin panel. Modules register admin sections via `#[AdminSection]` attributes, each containing menu items with permission-based visibility. The `AdminSectionRegistry` collects all sections and serves them sorted by priority. This is an interface/contracts package; install `marko/admin-panel` or `marko/admin-api` for the actual admin UI.

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

Use `#[AdminPermission]` to declare permissions that your section requires:

```php
use Marko\Admin\Attributes\AdminPermission;

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
use Marko\Admin\Contracts\AdminSectionRegistryInterface;

class NavigationBuilder
{
    public function __construct(
        private readonly AdminSectionRegistryInterface $sectionRegistry,
    ) {}

    public function buildMenu(): array
    {
        $sections = $this->sectionRegistry->all(); // sorted by sortOrder

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

### Attributes

```php
#[AdminSection(id: 'section-id', label: 'Label', icon: 'icon', sortOrder: 0)]
#[AdminPermission(id: 'section.action', label: 'Human Label')]
```
