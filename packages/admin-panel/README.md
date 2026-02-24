# Marko Admin Panel

Server-rendered admin panel UI--provides login, dashboard, and permission-filtered sidebar navigation out of the box.

## Overview

Admin Panel delivers the server-rendered HTML interface for the admin area. It includes a login/logout flow, a dashboard that displays registered admin sections, and a menu builder that filters navigation items by the current user's permissions. Templates are rendered via `ViewInterface` using the `admin-panel::` namespace. All routes are protected by `AdminAuthMiddleware`.

## Installation

```bash
composer require marko/admin-panel
```

Requires `marko/admin`, `marko/admin-auth`, and a view driver (e.g., `marko/view-latte`).

## Usage

### Built-in Routes

The panel registers these routes automatically:

| Method | Path | Description |
|--------|------|-------------|
| GET | `/admin/login` | Login form |
| POST | `/admin/login` | Authenticate |
| POST | `/admin/logout` | Logout |
| GET | `/admin` | Dashboard (requires auth) |

### Building the Sidebar Menu

`AdminMenuBuilderInterface` produces a navigation structure filtered by the user's permissions:

```php
use Marko\AdminPanel\Menu\AdminMenuBuilderInterface;
use Marko\AdminAuth\Entity\AdminUserInterface;

class LayoutHelper
{
    public function __construct(
        private readonly AdminMenuBuilderInterface $menuBuilder,
    ) {}

    public function getSidebar(
        AdminUserInterface $user,
        string $currentPath,
    ): array {
        return $this->menuBuilder->build($user, $currentPath);
        // Returns: [
        //   ['section' => ..., 'items' => [...], 'active' => true, 'activeItemId' => 'products'],
        //   ['section' => ..., 'items' => [...], 'active' => false, 'activeItemId' => null],
        // ]
    }
}
```

Each entry includes:
- `section` -- the `AdminSectionInterface` instance
- `items` -- filtered and sorted `MenuItemInterface` array
- `active` -- whether this section contains the current page
- `activeItemId` -- the ID of the active menu item, or null

### Dashboard Sections

Get sections the user can access for the dashboard overview:

```php
$sections = $this->menuBuilder->buildDashboardSections($user);
// Returns AdminSectionInterface[] with at least one accessible menu item
```

### Template Overrides

Admin panel templates live under the `admin-panel::` namespace. Override them by placing templates in your module's `resources/views/` directory with the same path:

```
mymodule/
  resources/
    views/
      admin-panel/
        dashboard/
          index.latte    # Overrides the default dashboard
```

## Customization

Replace the menu builder via Preferences to customize navigation behavior:

```php
use Marko\Core\Attributes\Preference;
use Marko\AdminPanel\Menu\AdminMenuBuilder;

#[Preference(replaces: AdminMenuBuilder::class)]
class CustomMenuBuilder extends AdminMenuBuilder
{
    public function build(
        AdminUserInterface $user,
        string $currentPath = '',
    ): array {
        $menu = parent::build($user, $currentPath);
        // Add custom sections or reorder
        return $menu;
    }
}
```

## API Reference

### AdminMenuBuilderInterface

```php
interface AdminMenuBuilderInterface
{
    public function build(AdminUserInterface $user, string $currentPath = ''): array;
    public function buildDashboardSections(AdminUserInterface $user): array;
}
```

### LoginController Routes

```php
#[Get(path: '/admin/login')]   // showLoginForm
#[Post(path: '/admin/login')]  // authenticate
#[Post(path: '/admin/logout')] // logout
```

### DashboardController Routes

```php
#[Get(path: '/admin')]                     // index
#[Middleware(AdminAuthMiddleware::class)]   // requires auth
```

### AdminPanelConfigInterface

```php
interface AdminPanelConfigInterface
{
    public function getPageTitle(): string;
    public function getItemsPerPage(): int;
}
```
