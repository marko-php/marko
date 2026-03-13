# marko/admin-panel

Server-rendered admin panel UI — provides login, dashboard, and permission-filtered sidebar navigation out of the box.

## Installation

```bash
composer require marko/admin-panel
```

## Quick Example

```php
use Marko\AdminPanel\Menu\AdminMenuBuilderInterface;
use Marko\AdminAuth\Entity\AdminUserInterface;

class LayoutHelper
{
    public function __construct(
        private readonly AdminMenuBuilderInterface $adminMenuBuilder,
    ) {}

    public function getSidebar(
        AdminUserInterface $user,
        string $currentPath,
    ): array {
        return $this->adminMenuBuilder->build($user, $currentPath);
    }
}
```

## Documentation

Full usage, API reference, and examples: [marko/admin-panel](https://marko.build/docs/packages/admin-panel/)
