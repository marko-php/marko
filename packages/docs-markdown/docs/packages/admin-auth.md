---
title: marko/admin-auth
description: Admin authentication and role-based authorization --- manages admin users, roles, permissions, and access control for the admin panel.
---

Admin authentication and role-based authorization --- manages admin users, roles, permissions, and access control for the admin panel. The package provides an `AdminUserProvider` that integrates with the [authentication](/docs/packages/authentication/) system, a `PermissionRegistry` for declaring and matching permissions (including wildcards), role and permission entities with repository interfaces, and `AdminAuthMiddleware` that enforces `#[RequiresPermission]` checks on controller classes and methods. Super admin roles bypass all permission checks.

## Installation

```bash
composer require marko/admin-auth
```

Then create the tables:

```bash
marko db:migrate
```

The package ships no migration files. Its tables come from its entities, which `db:migrate` discovers in `vendor/marko/admin-auth/src/Entity` like any other module's. In development (or with `--generate`) the command generates a migration for them in `database/migrations/` and applies it; commit that file and deploy it like your own migrations. See [database](/docs/packages/database/) for how migrations are generated.

| Table | Entity | Holds |
|-------|--------|-------|
| `roles` | `Role` | Roles and the `is_super_admin` flag (unique `slug`) |
| `permissions` | `Permission` | Permission keys, labels and groups (unique `key`, index on `group`) |
| `role_permissions` | `RolePermission` | Role to permission assignments (unique `role_id`, `permission_id`; index on `permission_id`) |
| `admin_users` | `AdminUser` | Admin users (unique `email`), with the remember-me token hash and its `remember_token_expires_at` |
| `admin_user_roles` | `AdminUserRole` | User to role assignments (unique `user_id`, `role_id`; index on `role_id`) |
| `remember_tokens` | `RememberToken` | One remember-me token per device: guard, user ID, unique `selector`, `validator_hash`, `expires_at` (indexed), `user_agent` |

Both pivots cascade: deleting a role, permission or admin user deletes its assignment rows.

### Remember-Me Tokens

The module binds `RememberTokenStorageInterface` to `RememberTokenRepository`, so remember-me tokens live in the `remember_tokens` table, one row per device. Logging in with "remember me" on a second browser no longer logs the first one out, and logging out deletes only that browser's row. The binding covers every session guard in the app, not just `admin`; the `guard` column keeps each guard's tokens apart. See [Per-Device Tokens](/docs/packages/authentication/#per-device-tokens) for how the cookie is checked and rotated.

Run `marko db:migrate` after upgrading to create the table, before deploying the new code: remember-me logins write to it. Admins remembered through the old `admin_users.remember_token` column stay signed in; their next visit moves them onto a device row and clears the column. Schedule `marko auth:clear-tokens` to purge rows that expired on devices that never came back.

:::note
`admin_users.remember_token_expires_at` holds when an admin's remember-me token stops being accepted; the expiry is enforced server-side rather than trusted from the cookie. Run `marko db:migrate` after upgrading to add the column. Remember tokens issued before the column existed have no expiry and are rejected, so admins who were remembered sign in again once.
:::

:::note
Earlier versions shipped hand-written MySQL migrations in `database/migrations/` that `db:migrate` never ran, so `admin_user_roles` was never created and the first admin login failed. If you created the tables from that SQL by hand, the entities differ from them: `role_permissions` and `admin_user_roles` have an auto-increment `id` primary key, `roles.is_super_admin` and `admin_users.is_active` are string columns (`'1'`/`'0'`) instead of `TINYINT(1)`, and the ids and timestamps use the entity column types. `marko db:migrate` generates and applies all of these changes. It adds each `id` column together with its primary key in one statement and numbers the existing rows, so the step earlier versions of this note asked for (adding the two `id` columns by hand first) is no longer needed. See [Primary Keys on Existing Tables](/docs/packages/database/#primary-keys-on-existing-tables). Review the generated migrations before you commit them.
:::

## Usage

### The Admin Guard

The admin area authenticates on its own guard, never the application's default guard. The package merges this guard into `authentication` config:

```php title="vendor/marko/admin-auth/config/authentication.php"
return [
    'guards' => [
        'admin' => ['driver' => 'session', 'provider' => 'admins'],
    ],
    'providers' => [
        'admins' => ['class' => AdminUserProvider::class],
    ],
];
```

`admin-auth.guard` names the guard the admin area uses (`admin` by default):

```php title="config/admin-auth.php"
return [
    'guard' => 'admin',
];
```

`AdminAuthMiddleware`, the [admin panel](/docs/packages/admin-panel/) login and logout, and the [admin API](/docs/packages/admin-api/) resolve that guard by name through `AdminGuardResolver` (`AuthManager::guard($config->getGuardName())`). Because the `admin` guard has its own provider and its own session key (`auth_admin_user_id`), the admin and frontend logins are separate: a customer logged in on the frontend is a guest in the admin area, and an admin login never logs into the frontend. The package does not bind `UserProviderInterface`, so your frontend provider binding stays in place. See [User Providers](/docs/packages/authentication/#user-providers).

`AdminUserProvider` checks admin passwords through [`PasswordVerifierInterface`](/docs/packages/authentication/#password-confirmation), the same single password check the rest of the framework uses. A login for an unknown or inactive admin, or with a password that isn't a string, still pays for one dummy hash check, so response timing doesn't reveal which admin accounts exist. Rebinding `PasswordVerifierInterface` changes how admin logins are checked too.

Resolve the admin guard in your own admin code the same way:

```php title="ReportController.php"
use Marko\AdminAuth\AdminGuardResolver;

readonly class ReportController
{
    public function __construct(
        private AdminGuardResolver $adminGuard,
    ) {}

    public function index(): Response
    {
        $admin = $this->adminGuard->guard()->user();
        // ...
    }
}
```

To serve the admin area through a token guard instead, for example a headless admin on [`marko/admin-api`](/docs/packages/admin-api/), define a token guard on the `admins` provider and point `admin-auth.guard` at it:

```php title="config/authentication.php"
return [
    'guards' => [
        'admin-api' => ['driver' => 'token', 'provider' => 'admins'],
    ],
];
```

```php title="config/admin-auth.php"
return [
    'guard' => 'admin-api',
];
```

In tests, authenticate an admin on the admin guard with the [HTTP test client](/docs/packages/testing/): `actingAs($admin, guard: 'admin')`.

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

When no admin is logged in, `AdminAuthMiddleware` does one of two things:

- **Redirects** to `{prefix}/login` (`/admin/login` by default) when the guard is stateful (the session guard) and the request does not want JSON.
- **Throws a `401` `UnauthenticatedException`** (an `HttpException` from `marko/authentication`) in every other case: when the request wants JSON (`Request::wantsJson()`: an `Accept` header with `application/json` or a `+json` type), and always when the guard is stateless (`StatelessGuardInterface`, such as the token guard), whatever the `Accept` header, because an API client can't follow a login redirect. On a stateless guard the `401` carries the guard's `WWW-Authenticate` challenge (`Bearer` for the token guard), the same response [`AuthMiddleware`](/docs/packages/authentication/) sends.

`AdminAuthMiddleware` uses the [admin guard](#the-admin-guard) (`admin-auth.guard`), so when that guard is a token guard, such as a headless admin served through [`marko/admin-api`](/docs/packages/admin-api/), it never redirects.

A user the admin guard authenticates that is not an `AdminUserInterface` gets a `403` on every admin route, whether or not the route requires a permission.

The routing pipeline renders the thrown `401` through [`ExceptionRenderer`](/docs/packages/routing/#errors-and-http-exceptions), so it looks like every other HTTP error in the application:

```json
{"message": "Unauthorized."}
```

### Requiring Permissions

Use `#[RequiresPermission]` to enforce specific permissions on a route. `AdminAuthMiddleware` reads the attribute from the matched controller method, or from the controller class when the method has none, and throws a `403` `HttpException` when the authenticated user lacks the required permission, or is not an admin user at all. Super admin roles bypass this check. A role is a super admin role when its `is_super_admin` flag (`roles.is_super_admin`) is set; there is no config key for it (the `admin-auth.super_admin_role` key earlier versions shipped was never read and has been removed).

`ExceptionRenderer` renders the `403` as JSON for requests that ask for it and as the application's HTML error page otherwise:

```json
{"message": "Forbidden."}
```

The required permission is never sent to the client. It is only in the exception's context, for logs. To render admin denials differently (a branded page, another JSON shape), replace `ExceptionRenderer` with a `#[Preference]`; see [Custom error pages](/docs/packages/routing/#custom-error-pages). Admin denials go through it like any other HTTP error.

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

Put `#[RequiresPermission]` on the controller class to protect every action at once. A method-level attribute replaces the class-level one for that action, it does not add to it:

```php title="SettingsController.php"
use Marko\AdminAuth\Attributes\RequiresPermission;
use Marko\AdminAuth\Middleware\AdminAuthMiddleware;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;

#[Middleware(AdminAuthMiddleware::class)]
#[RequiresPermission(permission: 'settings.manage')]
class SettingsController
{
    #[Get('/admin/settings')]
    public function index(): Response
    {
        // Requires 'settings.manage'
    }

    #[Get('/admin/settings/summary')]
    #[RequiresPermission(permission: 'settings.view')]
    public function summary(): Response
    {
        // Requires only 'settings.view'
    }
}
```

### Registering Permissions

The usual way to declare a permission is `#[AdminPermission]` on an admin section class (see [marko/admin](/docs/packages/admin/)):

```php title="app/catalog/src/Admin/CatalogSection.php"
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

You don't register these yourself. `marko/admin-auth`'s boot callback registers every `#[AdminPermission]` on the `#[AdminSection]` classes that [marko/admin](/docs/packages/admin/) discovers. It uses the same section list as marko/admin: one scan per boot, or the discovery cache in production. Each permission's group is the first segment of its key (`catalog` for `catalog.products.view`). Boot fails with an `AdminAuthException` when:

- a key isn't in the [permission key format](#keys-slugs-and-emails) (the message names the key and the section class);
- two section classes declare the same key (the message names both classes);
- a key declared by `#[AdminPermission]` was already registered by hand. Remove the manual `register()` call.

For a permission that doesn't belong to a section, call `PermissionRegistryInterface::register()` from your module's `boot` callback:

```php title="app/catalog/module.php"
use Marko\AdminAuth\Contracts\PermissionRegistryInterface;

return [
    'boot' => function (PermissionRegistryInterface $permissionRegistry): void {
        $permissionRegistry->register(
            key: 'catalog.reports.export',
            label: 'Export Reports',
            group: 'catalog',
        );
    },
];
```

Both ways register in memory only, and boot never touches the database. Roles are assigned permissions from the `permissions` table. After deploying code that adds, changes or removes permissions, write them to the table as described in [Syncing Permissions to the Database](#syncing-permissions-to-the-database).

`marko/admin-auth` binds `PermissionRegistryInterface` to `PermissionRegistry` as a shared singleton, so you don't bind it yourself. Every class that injects the interface gets the same instance: the boot callback, your own boot code, `AdminAuthMiddleware` and `marko/admin-api`'s `SectionController`. A permission registered through one of them is visible to all of them.

To replace the registry, put a `#[Preference]` on a class that implements the interface, and have it replace the interface rather than `PermissionRegistry`. The container checks preferences for the type being injected, which is the interface, so a Preference on the concrete class is never used. The replacement is still shared:

```php title="app/admin/src/AppPermissionRegistry.php"
use Marko\AdminAuth\Contracts\PermissionRegistryInterface;
use Marko\AdminAuth\PermissionRegistry;
use Marko\Core\Attributes\Preference;

#[Preference(replaces: PermissionRegistryInterface::class)]
class AppPermissionRegistry extends PermissionRegistry
{
    // Override register(), all(), getByGroup() or matches() as needed
}
```

### Syncing Permissions to the Database

```bash
marko admin-auth:permissions:sync
```

The command calls `PermissionRepositoryInterface::syncFromRegistry()`, which writes in one transaction:

- It inserts every registered permission that isn't in the table yet.
- It updates the `label` and `group` of an existing row when the `#[AdminPermission]` (or `register()` call) changed them.
- It renames a row whose key differs from a registered key only in letter case (`Posts.Edit` stored, `posts.edit` registered) to the registered key, and reports it as updated. The row keeps its id and role assignments. Without this, the insert would fail on MySQL/MariaDB's case-insensitive unique index and add a second row on PostgreSQL. If several case variants are stored (only possible on PostgreSQL), the one with the lowest id is renamed and the others are reported as unregistered.
- It reports every row whose key is no longer registered (a removed or renamed permission, or an uninstalled module) and how many roles still hold it.
- It deletes nothing.

```
Synced 12 registered permission(s): 1 created, 1 updated, 10 unchanged.
2 permission(s) in the database are no longer registered:
  legacy.reports.export (held by 3 role(s))
  legacy.reports.view (held by 0 role(s))
Re-run with --prune to delete them and their role assignments.
Wildcard grants kept: catalog.*
```

A stale row still grants its key: users with a role that holds it keep the permission, and a later module that registers the same key would hand its new meaning to those roles. Remove stale rows with `--prune`:

```bash
marko admin-auth:permissions:sync --prune
```

`--prune` deletes the unregistered permissions and their `role_permissions` rows in one `transaction()`, then lists what it removed. It deletes the role assignments explicitly, so the result doesn't depend on the foreign key's `ON DELETE CASCADE`.

Keys containing `*` are wildcard grants such as `catalog.*` or `*` (see [Wildcard Permissions](#wildcard-permissions)). `#[AdminPermission]` never registers them, so they are never reported as stale and never pruned. They are listed as "Wildcard grants kept".

Pruning changes what roles can do, so it follows the [destructive command policy](/docs/packages/database/#environment-behaviour) of marko/database, opted in to run in production:

| Environment | `--prune` |
|-------------|-----------|
| `development`, `dev`, `local`, `testing`, `test` | Runs; asks for confirmation first when someone can answer |
| Everything else, including `production` and an unset environment | Refused with exit code 1 unless you also pass `--force`. With `--force`, asks for confirmation when someone can answer, and runs when nobody can (deploy scripts, `--no-interaction`) |

Declining the confirmation cancels the prune with exit code 0; the sync itself has already been written. A deploy script that prunes passes both flags:

```bash
APP_ENV=production marko admin-auth:permissions:sync --prune --force --no-interaction
```

When nothing is stale, `--prune` prints `No unregistered permissions to prune.` and needs no `--force`. The plain sync never deletes anything, so running it on every deploy is safe: a deploy that temporarily disables a module doesn't strip its permissions from roles.

Every run dispatches [`PermissionsSynced`](#events).

### Keys, Slugs and Emails

Permission keys, role slugs and admin emails are unique columns. MySQL and MariaDB compare them with the server's default collation, which ignores case (and accents), while PostgreSQL and PHP compare them exactly (see [String Comparison and Collation](/docs/packages/database/#string-comparison-and-collation)). So that `posts.edit` and `Posts.Edit` mean the same thing on every driver, admin-auth enforces one canonical form in PHP, before any SQL runs:

| Value | Format | Enforced by |
|-------|--------|-------------|
| Permission key | Lowercase segments of `a-z`, `0-9`, `_` and `-` separated by dots (`catalog.products.view`). Segments after the first may contain `*` (`catalog.*`, `catalog.products.ed*`), and `*` alone grants everything | `PermissionRegistryInterface::register()`, `#[AdminPermission]` discovery, `PermissionRepository::save()`/`insertBatch()` throw `AdminAuthException::invalidPermissionKey()`; `findByKey()` returns `null` |
| Role slug | Lowercase segments of `a-z`, `0-9`, `_` and `-` separated by dots (`content-editor`), no `*` | `RoleRepository::save()`/`insertBatch()` and `isSlugUnique()` throw `AdminAuthException::invalidRoleSlug()`; `findBySlug()` returns `null` |
| Admin email | Lowercased with `mb_strtolower()` | `AdminUserRepository::save()`/`insertBatch()` store it lowercased and `findByEmail()` looks it up lowercased |

The patterns are `IdentifierFormat::PERMISSION_KEY_PATTERN` and `IdentifierFormat::ROLE_SLUG_PATTERN`; check a value with `IdentifierFormat::isPermissionKey()` or `IdentifierFormat::isRoleSlug()`.

Because emails are lowercased, an admin signs in with `Mark@Example.com` or `mark@example.com` alike on every driver, and saving a second admin whose email differs only in case throws `UniqueConstraintViolationException` on every driver.

:::note
**Upgrading.** Keys and slugs that earlier versions accepted are now rejected:

- A registered or `#[AdminPermission]` key with uppercase letters or spaces fails the boot. Lowercase it in the code. Stored rows that differ from a registered key only in case are renamed by the next `marko admin-auth:permissions:sync`, keeping their role assignments. Any other stored key outside the format (a wildcard grant such as `Catalog.*`) matches nothing; rename it by hand.
- Saving a role whose stored slug is outside the format (such as `Editor`) throws, on every driver. Lowercase the stored slugs (on PostgreSQL, first merge any two slugs that differ only in case, found as for emails below):

  ```sql
  UPDATE roles SET slug = LOWER(slug);
  ```

  Slugs with spaces or other characters need a new slug chosen by hand. Update any code that calls `hasRole()` or `findBySlug()` with the old slug.
- Emails stored with uppercase letters aren't found by `findByEmail()` on PostgreSQL until they are lowercased. PostgreSQL may also hold two case variants of one address, which the `UPDATE` would reject on the unique index, so find and merge those first:

  ```sql
  SELECT LOWER(email) AS email, COUNT(*) FROM admin_users GROUP BY LOWER(email) HAVING COUNT(*) > 1;
  UPDATE admin_users SET email = LOWER(email);
  ```
:::

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
use Marko\Routing\Exceptions\HttpException;

class OrderService
{
    public function cancel(
        AdminUserInterface $adminUser,
        int $orderId,
    ): void {
        if (!$adminUser->hasPermission('orders.cancel')) {
            throw HttpException::forbidden('Cannot cancel orders.');
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
use Marko\AdminAuth\AdminGuardResolver;
use Marko\AdminAuth\Entity\AdminUserInterface;

readonly class DashboardController
{
    public function __construct(
        private AdminGuardResolver $adminGuard,
    ) {}

    public function index(): Response
    {
        $user = $this->adminGuard->guard()->user();

        if ($user instanceof AdminUserInterface) {
            $name = $user->getName();
            $roles = $user->getRoles();
            $permissions = $user->getPermissionKeys();
        }
    }
}
```

### Events

`RoleRepository` and `AdminUserRepository` dispatch `RoleCreated`, `RoleUpdated`, `RoleDeleted`, `AdminUserCreated` and `AdminUserUpdated` after a save or delete. `AdminUserDeleted` is available for your own code to dispatch. Each event exposes `getTimestamp()`, which is a required constructor argument: the events never read the clock themselves. The repositories pass the current instant from the [`marko/database`](/docs/packages/database/) repository (`Repository::now()`, in UTC), the same instant source used for `#[Timestamps]`.

`admin-auth:permissions:sync` dispatches `PermissionsSynced` once per run, after the sync and any prune, with the time from the injected `ClockInterface`. It fires even when `--prune` is refused or cancelled, because the sync has already been written; `getPrunedCount()` is then `0`. Observe it to audit permission changes:

```php title="app/admin/src/Observers/AuditPermissionSync.php"
use Marko\AdminAuth\Events\PermissionsSynced;
use Marko\Core\Attributes\Observer;
use Marko\Log\Contracts\LoggerInterface;

#[Observer(event: PermissionsSynced::class)]
readonly class AuditPermissionSync
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function handle(
        PermissionsSynced $event,
    ): void {
        $this->logger->info('Admin permissions synced', [
            'registered' => $event->getTotalCount(),
            'created' => $event->getCreatedCount(),
            'updated' => $event->getUpdatedCount(),
            'unregistered' => $event->getUnregisteredCount(),
            'pruned' => $event->getPrunedCount(),
            'at' => $event->getTimestamp()->format(DATE_ATOM),
        ]);
    }
}
```

| Getter | Returns |
|--------|---------|
| `getTotalCount()` | The number of registered permissions |
| `getCreatedCount()` | Permissions inserted |
| `getUpdatedCount()` | Existing permissions whose label or group changed |
| `getUnregisteredCount()` | Rows no longer registered, wildcard grants excluded |
| `getPrunedCount()` | Rows deleted by `--prune` (`0` without it, or when it was refused or cancelled) |

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

`register()` throws `AdminAuthException::invalidPermissionKey()` for a key outside the [permission key format](#keys-slugs-and-emails) and `AdminAuthException::duplicatePermission()` for a key that is already registered.

### PermissionDiscovery

```php
public function registerFromDefinitions(array $definitions): void;
public function discoverFromClass(string $className): void;
```

`registerFromDefinitions()` registers the `#[AdminPermission]` entries of the given `AdminSectionDefinition`s, grouped by the first segment of each key. The boot callback calls it with marko/admin's `DiscoveredAdminSections::all()`. `discoverFromClass()` parses one `#[AdminSection]` class and registers its permissions.

| Exception | Thrown when |
|-----------|-------------|
| `AdminAuthException::invalidPermissionKey()` | A declared key is outside the [permission key format](#keys-slugs-and-emails) (the message names the key and the section class). Nothing is registered |
| `AdminAuthException::duplicatePermission()` | Two section classes declare the same permission key (the message names both classes) |
| `AdminAuthException::permissionAlreadyRegistered()` | A key declared by `#[AdminPermission]` was already registered by hand |

### Commands

| Command | Description |
|---------|-------------|
| `admin-auth:permissions:sync` | Inserts missing permissions, updates changed labels and groups, and lists permissions that are no longer registered with how many roles hold each. Deletes nothing |
| `admin-auth:permissions:sync --prune` | Also deletes the unregistered permissions and their role assignments in one transaction. Never deletes a key containing `*`. Outside development and testing, needs `--force`; asks for confirmation when someone can answer |
| `auth:clear-tokens` | From marko/authentication: deletes expired rows from `remember_tokens` (`--force` deletes every row). See [Remember-Me Tokens](#remember-me-tokens) |

### RequiresPermission Attribute

```php
#[RequiresPermission(permission: 'section.action')]
```

Targets classes and methods. A method-level attribute replaces a class-level one.

### AdminAuthMiddleware

```php
class AdminAuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response;
}
```

Throws `Marko\Authentication\Exceptions\UnauthenticatedException` with status `401` for an unauthenticated request that wants JSON or whose guard is stateless (with the guard's `WWW-Authenticate` challenge when the guard is stateless), or `Marko\Routing\Exceptions\HttpException` with status `403` for a user that is not an `AdminUserInterface` or lacks the required permission. An unauthenticated request on a stateful guard that doesn't want JSON gets a redirect response to `{prefix}/login`; a stateless guard never redirects.

Permission enforcement relies on the router attaching route context to the request before middleware runs (see [`marko/routing`](/docs/packages/routing/) --- `Request::withRoute()`). If no route context is present, `#[RequiresPermission]` is not evaluated and the request passes through authenticated.

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
    /** @return array<Permission> */
    public function getPermissionsForRoles(array $roleIds): array;
    public function syncPermissions(int $roleId, array $permissionIds): void;
    public function isSlugUnique(string $slug, ?int $excludeId = null): bool;
}

interface PermissionRepositoryInterface extends RepositoryInterface
{
    public function findByKey(string $key): ?Permission;
    public function findByGroup(string $group): array;
    public function syncFromRegistry(PermissionRegistryInterface $registry): PermissionSyncResult;
    /** @return list<UnregisteredPermission> */
    public function findUnregistered(PermissionRegistryInterface $registry): array;
    /** @return list<UnregisteredPermission> */
    public function pruneUnregistered(PermissionRegistryInterface $registry): array;
}
```

`findByEmail()` lowercases the email before the lookup. `findBySlug()` and `findByKey()` return `null` for a slug or key outside the [canonical format](#keys-slugs-and-emails) without querying, and `isSlugUnique()` throws `AdminAuthException::invalidRoleSlug()` for one, since `save()` would reject it.

`syncFromRegistry()` inserts the registered permissions that are missing from the table, updates the label and group of rows that changed and renames rows whose key differs from a registered key only in case, in one transaction. It deletes nothing. It returns a `PermissionSyncResult`:

```php
readonly class PermissionSyncResult
{
    public int $registeredCount;
    public array $created;      // list<string>: keys inserted
    public array $updated;      // list<string>: keys whose label or group changed, or whose case was repaired
    public array $unregistered; // list<UnregisteredPermission>: rows no longer registered
    public array $wildcardKeys; // list<string>: keys containing `*`, kept

    public function createdCount(): int;
    public function updatedCount(): int;
    public function unregisteredCount(): int;
}

readonly class UnregisteredPermission
{
    public int $id;
    public string $key;
    public string $label;
    public string $group;
    public int $roleCount; // distinct roles holding the permission
}
```

`findUnregistered()` returns the rows whose key is not registered, sorted by key. `pruneUnregistered()` deletes those rows and their `role_permissions` rows in one `transaction()` (a savepoint inside your own transaction) and returns what it removed. Neither ever includes a key containing `*`. The deletes are SQL statements, so no `EntityDeleting` or `EntityDeleted` events fire for the removed rows. The `admin-auth:permissions:sync` command calls `syncFromRegistry()`, and `pruneUnregistered()` with `--prune`.

`getPermissionsForRoles()` returns the deduplicated permission set across all given role IDs in a single query. Empty input returns an empty array without issuing a query.

`syncPermissions()` replaces a role's permissions and `syncRoles()` replaces an admin user's roles. Each deletes the existing rows and inserts the new set in batches, all inside one `transaction()`. A failure part-way through rolls the whole sync back, so a role is never left half-synced and a user keeps their previous roles when an id is unknown or repeated (the foreign key or the unique index rejects it). Called inside your own transaction, the sync runs in a savepoint. If it fails, only the sync's changes are undone, and you can catch the exception and still commit the rest of your transaction.

### AdminGuardResolver

```php
readonly class AdminGuardResolver
{
    public function guard(): GuardInterface; // AuthManager::guard(admin-auth.guard)
}
```

### AdminAuthConfigInterface

```php
interface AdminAuthConfigInterface
{
    public function getGuardName(): string;
}
```
