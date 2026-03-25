---
title: marko/admin-auth
description: Admin authentication and role-based authorization --- manages admin users, roles, permissions, and access control for the admin panel.
---

Admin authentication and role-based authorization --- manages admin users, roles, permissions, and access control for the admin panel. The package provides an `AdminUserProvider` that integrates with the [authentication](/docs/packages/authentication/) system, a `PermissionRegistry` for declaring and matching permissions (including wildcards), role and permission entities with repository interfaces, and `AdminAuthMiddleware` that enforces `#[RequiresPermission]` checks on controller methods. Super admin roles bypass all permission checks.

## Installation

```bash
composer require marko/admin-auth
```

## Usage

### Protecting Admin Routes

Add `AdminAuthMiddleware` to controller methods or classes to require authentication:

```php title="CatalogController.php"
use Marko\AdminAuth\Middleware\AdminAuthMiddleware;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;

class CatalogController
{
    #[Get('/admin/catalog/products')]
    #[Middleware(AdminAuthMiddleware::class)]
    public function index(): Response
    {
        // Only authenticated admin users reach here
    }
}
```

Unauthenticated requests are redirected to the admin login page (or receive a 401 JSON response for API requests).

### Requiring Permissions

Use `#[RequiresPermission]` to enforce specific permissions on a route:

```php title="ProductController.php"
use Marko\AdminAuth\Attributes\RequiresPermission;
use Marko\AdminAuth\Middleware\AdminAuthMiddleware;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;

class ProductController
{
    #[Get('/admin/catalog/products')]
    #[Middleware(AdminAuthMiddleware::class)]
    #[RequiresPermission(permission: 'catalog.products.view')]
    public function index(): Response
    {
        // Only admin users with 'catalog.products.view' permission
    }
}
```

### Registering Permissions

Modules register their permissions via `PermissionRegistryInterface`:

```php title="CatalogPermissions.php"
use Marko\AdminAuth\Contracts\PermissionRegistryInterface;

class CatalogPermissions
{
    public function __construct(
        private readonly PermissionRegistryInterface $permissionRegistry,
    ) {}

    public function register(): void
    {
        $this->permissionRegistry->register(
            'catalog.products.view',
            'View Products',
            'Catalog',
        );
        $this->permissionRegistry->register(
            'catalog.products.edit',
            'Edit Products',
            'Catalog',
        );
    }
}
```

### Wildcard Permissions

Permissions support wildcard matching. A role with `catalog.*` can access any `catalog.` permission:

```php
use Marko\AdminAuth\Contracts\PermissionRegistryInterface;

$permissionRegistry->matches('catalog.*', 'catalog.products.view');  // true
$permissionRegistry->matches('catalog.*', 'catalog.products.edit');   // true
$permissionRegistry->matches('*', 'anything.here');                   // true
$permissionRegistry->matches('catalog.products.*', 'catalog.orders'); // false
```

### Checking Permissions in Code

`AdminUserInterface` provides methods for checking permissions and roles:

```php title="OrderService.php"
use Marko\AdminAuth\Entity\AdminUserInterface;

class OrderService
{
    public function cancel(
        AdminUserInterface $adminUser,
        int $orderId,
    ): void {
        if (!$adminUser->hasPermission('orders.cancel')) {
            throw new AuthorizationException('Cannot cancel orders');
        }

        if ($adminUser->hasRole('super-admin')) {
            // super admin bypass
        }
    }
}
```

### Admin User Entity

`AdminUser` implements `AdminUserInterface` and integrates with the [authentication](/docs/packages/authentication/) system:

```php title="DashboardController.php"
use Marko\AdminAuth\Entity\AdminUserInterface;
use Marko\Authentication\Contracts\GuardInterface;

class DashboardController
{
    public function __construct(
        private readonly GuardInterface $guard,
    ) {}

    public function index(): Response
    {
        $user = $this->guard->user();

        if ($user instanceof AdminUserInterface) {
            $name = $user->getName();
            $roles = $user->getRoles();
            $permissions = $user->getPermissionKeys();
        }
    }
}
```

## API Reference

### AdminUserInterface

```php
interface AdminUserInterface extends AuthenticatableInterface
{
    public function getEmail(): string;
    public function getName(): string;
    public function setRoles(array $roles, array $permissionKeys = []): void;
    public function getRoles(): array;
    public function getPermissionKeys(): array;
    public function hasPermission(string $key): bool;
    public function hasRole(string $slug): bool;
}
```

### PermissionRegistryInterface

```php
interface PermissionRegistryInterface
{
    public function register(string $key, string $label, string $group): void;
    public function all(): array;
    public function getByGroup(string $group): array;
    public function matches(string $pattern, string $permissionKey): bool;
}
```

### RequiresPermission Attribute

```php
#[RequiresPermission(permission: 'section.action')]
```

### AdminAuthMiddleware

```php
class AdminAuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response;
}
```

### Repository Interfaces

```php
interface AdminUserRepositoryInterface extends RepositoryInterface
{
    public function findByEmail(string $email): ?AdminUser;
    public function getRolesForUser(int $userId): array;
    public function syncRoles(int $userId, array $roleIds): void;
}

interface RoleRepositoryInterface extends RepositoryInterface
{
    public function findBySlug(string $slug): ?Role;
    public function getPermissionsForRole(int $roleId): array;
    public function syncPermissions(int $roleId, array $permissionIds): void;
    public function isSlugUnique(string $slug, ?int $excludeId = null): bool;
}

interface PermissionRepositoryInterface extends RepositoryInterface
{
    public function findByKey(string $key): ?Permission;
    public function findByGroup(string $group): array;
    public function syncFromRegistry(PermissionRegistryInterface $registry): void;
}
```

### AdminAuthConfigInterface

```php
interface AdminAuthConfigInterface
{
    public function getGuardName(): string;
    public function getSuperAdminRoleSlug(): string;
}
```
