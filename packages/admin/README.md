# marko/admin

Admin contracts and section registry -- defines the structure for admin sections, menu items, and dashboard widgets so any module can contribute to the admin panel.

## Installation

```bash
composer require marko/admin
```

## Quick Example

```php
use Marko\Admin\Attributes\AdminSection;
use Marko\Admin\Contracts\AdminSectionInterface;
use Marko\Admin\MenuItem;

#[AdminSection(id: 'catalog', label: 'Catalog', icon: 'box', sortOrder: 20)]
class CatalogSection implements AdminSectionInterface
{
    public function getId(): string { return 'catalog'; }
    public function getLabel(): string { return 'Catalog'; }
    public function getIcon(): string { return 'box'; }
    public function getSortOrder(): int { return 20; }

    public function getMenuItems(): array
    {
        return [
            new MenuItem(id: 'products', label: 'Products', url: '/admin/catalog/products'),
        ];
    }
}
```

## Documentation

Full usage, API reference, and examples: [marko/admin](https://marko.build/docs/packages/admin/)
