---
title: Build an Admin Panel
description: Create a fully-featured admin panel with authentication, roles, permissions, and CRUD operations.
---

Build a secure admin panel for managing your application's data, complete with admin user authentication, role-based permissions, navigation sections, and CRUD operations.

## What You'll Build

- An admin panel with login/logout authentication
- Role-based access control with permissions
- Admin sections with sidebar navigation
- CRUD controllers protected by middleware
- An admin API for headless access

## Prerequisites

- PHP 8.5+
- Composer 2.x
- PostgreSQL (or MySQL)
- A Marko project (see [Installation](/docs/getting-started/installation/))

## Step 1: Install the Admin Packages

```bash
composer require marko/admin marko/admin-panel marko/admin-auth marko/admin-api \
    marko/authentication marko/authorization marko/routing marko/config \
    marko/database marko/session
```

The admin stack is split into focused packages:

- `marko/admin` --- core admin abstractions (sections, menu items, config)
- `marko/admin-panel` --- web-based admin UI (login, dashboard, menu builder)
- `marko/admin-auth` --- admin user entities, roles, permissions, and middleware
- `marko/admin-api` --- JSON API endpoints for the admin panel

## Step 2: Configure the Admin Panel

Create the admin configuration file:

```php title="config/admin.php"
<?php

declare(strict_types=1);

return [
    'route_prefix' => '/admin',
    'name' => 'My App Admin',
];
```

Set the title the dashboard shows in the browser tab:

```php title="config/admin-panel.php"
<?php

declare(strict_types=1);

return [
    'page_title' => 'My App Admin',
];
```

## Step 3: Configure Admin Authentication

Set the guard the admin area authenticates with:

```php title="config/admin-auth.php"
<?php

declare(strict_types=1);

return [
    'guard' => 'admin',
];
```

`marko/admin-auth` adds the `admin` guard to the authentication config itself: a session guard whose `admins` provider is `AdminUserProvider`. It is separate from your frontend guard, with its own session key, so admins and frontend users never share a login. You don't need to configure it. See [The Admin Guard](/docs/packages/admin-auth/#the-admin-guard).

## Step 4: Set Up the Database Schema

The admin auth system uses entity classes with `#[Table]`, `#[Column]`, and `#[Index]` attributes to define the database schema. These entities are provided by `marko/admin-auth` --- you do not need to create them yourself. Here is what they look like:

The `AdminUser` entity maps to the `admin_users` table:

```php title="packages/admin-auth/src/Entity/AdminUser.php"
<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('admin_users')]
class AdminUser extends Entity implements AdminUserInterface
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column(unique: true)]
    public string $email;

    #[Column]
    public string $password;

    #[Column]
    public string $name;

    #[Column]
    public ?string $rememberToken = null;

    #[Column(default: '1')]
    public string $isActive = '1';

    #[Column]
    public ?string $createdAt = null;

    #[Column]
    public ?string $updatedAt = null;

    // ... authentication and role/permission methods
}
```

The `Role`, `Permission`, and `RolePermission` entities follow the same pattern:

```php title="packages/admin-auth/src/Entity/Role.php"
<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('roles')]
class Role extends Entity implements RoleInterface
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column]
    public string $name;

    #[Column(unique: true)]
    public string $slug;

    #[Column(type: 'TEXT')]
    public ?string $description = null;

    #[Column(default: '0')]
    public string $isSuperAdmin = '0';

    #[Column]
    public ?string $createdAt = null;

    #[Column]
    public ?string $updatedAt = null;

    // ...
}
```

The `RolePermission` pivot entity uses `#[Index]` for the composite unique constraint and `#[Column]` with `references` for foreign keys:

```php title="packages/admin-auth/src/Entity/RolePermission.php"
<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Index;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('role_permissions')]
#[Index('idx_role_permissions_unique', ['role_id', 'permission_id'], unique: true)]
class RolePermission extends Entity implements RolePermissionInterface
{
    #[Column(references: 'roles.id', onDelete: 'CASCADE')]
    public int $roleId;

    #[Column(references: 'permissions.id', onDelete: 'CASCADE')]
    public int $permissionId;
}
```

Generate and run the migrations from these entity definitions:

```bash
marko db:migrate
```

Marko reads the `#[Table]`, `#[Column]`, `#[Index]`, and `#[ForeignKey]` attributes from your entity classes, diffs them against the current database state, and auto-generates the necessary migration SQL.

Seed a super admin role and an initial admin user:

```bash
marko db:seed
```

Or insert them manually:

```sql
INSERT INTO roles (name, slug, description, is_super_admin)
VALUES ('Super Admin', 'super-admin', 'Full access to all admin features', '1');

-- Password should be hashed with your PasswordHasherInterface implementation
INSERT INTO admin_users (email, password, name, is_active)
VALUES ('admin@example.com', '$2y$12$YOUR_HASHED_PASSWORD', 'Admin', '1');

INSERT INTO admin_user_roles (user_id, role_id) VALUES (1, 1);
```

## Step 5: Register Permissions

You declare permissions with `#[AdminPermission]` attributes on an admin section class, which you'll create in the next step (`posts.view`, `posts.create`, `posts.edit` and `posts.delete`). You don't register them yourself: at boot, `marko/admin-auth` registers every `#[AdminPermission]` in `PermissionRegistryInterface`, grouped by the first segment of the key (`posts`). Registering the same key again by hand fails boot with an `AdminAuthException`.

Boot keeps permissions in memory and never writes to the database. To assign them to roles, write them to the `permissions` table once the section exists, and again after each deploy that changes permissions:

```bash
marko admin-auth:permissions:sync
```

The command inserts the permissions that are missing from the table and updates changed labels and groups. It lists permissions that are no longer registered, but deletes them only when you add `--prune` (see [Syncing Permissions to the Database](/docs/packages/admin-auth/#syncing-permissions-to-the-database)).

## Step 6: Create an Admin Section

Admin sections organize your panel's sidebar navigation. Each section is a class that implements `AdminSectionInterface` and is decorated with the `#[AdminSection]` attribute:

```php title="app/admin/src/Section/PostsSection.php"
<?php

declare(strict_types=1);

namespace App\Admin\Section;

use Marko\Admin\Attributes\AdminPermission;
use Marko\Admin\Attributes\AdminSection;
use Marko\Admin\Contracts\AdminSectionInterface;
use Marko\Admin\Contracts\MenuItemInterface;
use Marko\Admin\MenuItem;

#[AdminSection(id: 'posts', label: 'Posts', icon: 'file-text', sortOrder: 10)]
#[AdminPermission(id: 'posts.view', label: 'View Posts')]
#[AdminPermission(id: 'posts.create', label: 'Create Posts')]
#[AdminPermission(id: 'posts.edit', label: 'Edit Posts')]
#[AdminPermission(id: 'posts.delete', label: 'Delete Posts')]
class PostsSection implements AdminSectionInterface
{
    public function getId(): string
    {
        return 'posts';
    }

    public function getLabel(): string
    {
        return 'Posts';
    }

    public function getIcon(): string
    {
        return 'file-text';
    }

    public function getSortOrder(): int
    {
        return 10;
    }

    /**
     * @return array<MenuItemInterface>
     */
    public function getMenuItems(): array
    {
        return [
            new MenuItem(
                id: 'posts-list',
                label: 'All Posts',
                url: '/admin/posts',
                icon: 'list',
                sortOrder: 0,
                permission: 'posts.view',
            ),
            new MenuItem(
                id: 'posts-create',
                label: 'Add New',
                url: '/admin/posts/create',
                icon: 'plus',
                sortOrder: 10,
                permission: 'posts.create',
            ),
        ];
    }
}
```

There is nothing to register. At boot, `marko/admin` finds every `#[AdminSection]` class in your modules' `src/` directories and registers it in the shared `AdminSectionRegistryInterface`. The section is built through the container the first time the admin needs it, not at boot, so its constructor can inject repositories without slowing down or breaking other requests and CLI commands. `getId()` must return the same id as the attribute (`posts`), or the first admin page that builds the section fails with an `AdminException`. In production, run `marko discovery:cache` after adding or changing a section so the cached section list stays current.

The `AdminMenuBuilder` from `marko/admin-panel` automatically filters menu items based on the current user's permissions --- users only see items they have access to.

## Step 7: Build an Admin Controller with CRUD

Create a controller with routes protected by `AdminAuthMiddleware`. Use the `#[RequiresPermission]` attribute for fine-grained permission checks, and `ViewInterface` to render Latte templates:

```php title="app/admin/src/Controller/PostController.php"
<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use Marko\AdminAuth\Attributes\RequiresPermission;
use Marko\AdminAuth\Middleware\AdminAuthMiddleware;
use Marko\Routing\Attributes\Delete;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Attributes\Post;
use Marko\Routing\Attributes\Put;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\View\ViewInterface;

#[Middleware(AdminAuthMiddleware::class)]
readonly class PostController
{
    public function __construct(
        private ViewInterface $view,
    ) {}

    #[Get(path: '/admin/posts')]
    #[RequiresPermission(permission: 'posts.view')]
    public function index(Request $request): Response
    {
        $posts = []; // Fetch from your repository

        return $this->view->render('admin::post/index', [
            'posts' => $posts,
        ]);
    }

    #[Get(path: '/admin/posts/create')]
    #[RequiresPermission(permission: 'posts.create')]
    public function create(Request $request): Response
    {
        return $this->view->render('admin::post/create');
    }

    #[Post(path: '/admin/posts')]
    #[RequiresPermission(permission: 'posts.create')]
    public function store(Request $request): Response
    {
        $title = $request->post('title');
        $body = $request->post('body');

        // Save the post to the database...

        return Response::redirect('/admin/posts');
    }

    #[Get(path: '/admin/posts/{id}')]
    #[RequiresPermission(permission: 'posts.edit')]
    public function edit(
        int $id,
        Request $request,
    ): Response {
        // Fetch post from your repository
        return $this->view->render('admin::post/edit', [
            'post' => $post,
        ]);
    }

    #[Put(path: '/admin/posts/{id}')]
    #[RequiresPermission(permission: 'posts.edit')]
    public function update(
        int $id,
        Request $request,
    ): Response {
        $title = $request->post('title');
        $body = $request->post('body');

        // Update the post in the database...

        return Response::redirect('/admin/posts');
    }

    #[Delete(path: '/admin/posts/{id}')]
    #[RequiresPermission(permission: 'posts.delete')]
    public function destroy(
        int $id,
        Request $request,
    ): Response {
        // Delete the post from the database...

        return Response::redirect('/admin/posts');
    }
}
```

The templates live in your module's `resources/views/` directory. For example, the post index template:

```latte title="app/admin/resources/views/post/index.latte"
{layout 'admin-panel::layout/base'}

{block content}
    <h1>All Posts</h1>

    <table>
        <thead>
            <tr>
                <th>Title</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            {foreach $posts as $post}
                <tr>
                    <td>{$post->getTitle()}</td>
                    <td>{$post->getStatus()->value}</td>
                    <td>
                        <a href="/admin/posts/{$post->getId()}">Edit</a>
                    </td>
                </tr>
            {/foreach}
        </tbody>
    </table>
{/block}
```

Templates use the `admin-panel::layout/base` layout provided by `marko/admin-panel`, which includes the sidebar navigation and common admin chrome. The `{block content}` section is where your page content goes.

The `#[Middleware(AdminAuthMiddleware::class)]` attribute on the class applies authentication to every route in this controller. The `AdminAuthMiddleware` does two things:

1. Checks that the user is logged in --- an unauthenticated browser request on the session guard is redirected to `/admin/login`, while a request that wants JSON, or any request on a stateless guard such as the token guard, gets a 401
2. Checks the `#[RequiresPermission]` attribute on each action --- users without the required permission get a 403 Forbidden response

Super admin users (those with a role where `isSuperAdmin` is true) automatically bypass all permission checks.

## Step 8: Use the Admin Login Flow

The `LoginController` from `marko/admin-panel` provides built-in login and logout routes:

| Route | Method | Action |
|---|---|---|
| `/admin/login` | GET | Show login form |
| `/admin/login` | POST | Authenticate with email/password |
| `/admin/logout` | POST | Log out the current admin user |

Authentication uses the [admin guard](/docs/packages/admin-auth/#the-admin-guard) (`admin-auth.guard`, separate from your frontend guard) --- the `LoginController` calls `$guard->attempt($credentials)` with email and password from the form, and `$guard->logout()` for sign-out. The `AdminUserProvider` handles credential verification, loads the user's roles, and aggregates permission keys from the role-permission pivot table.

## Step 9: Build the Admin Dashboard

The `DashboardController` from `marko/admin-panel` renders the admin dashboard at `/admin`, protected by `AdminAuthMiddleware`. It provides the registered sections and the current user to the view:

```php
use Marko\Admin\Contracts\AdminSectionRegistryInterface;
use Marko\AdminAuth\AdminGuardResolver;

// Inside the dashboard controller:
$sections = $sectionRegistry->all();            // Returns all sections sorted by sortOrder
$currentUser = $adminGuard->guard()->user();    // The authenticated AdminUser
```

Use the `AdminMenuBuilder` to construct the sidebar navigation filtered by the current user's permissions:

```php
use Marko\AdminPanel\Menu\AdminMenuBuilderInterface;
use Marko\AdminAuth\Entity\AdminUserInterface;

// Build the sidebar menu for the current admin user
$menu = $adminMenuBuilder->build(
    user: $currentUser,
    currentPath: '/admin/posts',
);

// Each menu entry contains:
// 'section' => AdminSectionInterface  (the section object)
// 'items'   => array<MenuItemInterface> (filtered by user permissions, sorted)
// 'active'  => bool (whether this section contains the active item)
// 'activeItemId' => string|null (ID of the active menu item)
```

You can also get the list of sections the user can access for dashboard cards:

```php
$dashboardSections = $adminMenuBuilder->buildDashboardSections(
    user: $currentUser,
);
```

## Step 10: Add an Admin API

The `marko/admin-api` package provides JSON API endpoints for headless admin access. It needs no configuration: its routes live under `/admin/api/v1` and authenticate on the same admin guard as the panel (`admin-auth.guard`).

The package includes two built-in controllers:

**`MeController`** --- returns the authenticated admin user's profile at `GET /admin/api/v1/me`:

```json
{
    "data": {
        "id": 1,
        "email": "admin@example.com",
        "name": "Admin",
        "roles": [
            { "id": 1, "name": "Super Admin", "slug": "super-admin" }
        ],
        "permissions": ["posts.view", "posts.create", "posts.edit", "posts.delete"]
    },
    "meta": {}
}
```

**`SectionController`** --- lists admin sections at `GET /admin/api/v1/sections` and shows a single section with its menu items at `GET /admin/api/v1/sections/{id}`.

Both controllers use `AdminAuthMiddleware`, so requests require a valid admin session. Use the `ApiResponse` helper for consistent JSON responses in your own admin API controllers:

```php title="app/admin/src/Controller/PostApiController.php"
<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use JsonException;
use Marko\AdminApi\ApiResponse;
use Marko\AdminAuth\Attributes\RequiresPermission;
use Marko\AdminAuth\Middleware\AdminAuthMiddleware;
use Marko\Routing\Attributes\Delete;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Attributes\Post;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

#[Middleware(AdminAuthMiddleware::class)]
class PostApiController
{
    #[Get(path: '/admin/api/v1/posts')]
    #[RequiresPermission(permission: 'posts.view')]
    public function index(): Response
    {
        $posts = []; // Fetch from your repository

        return ApiResponse::success(data: $posts);
    }

    /**
     * @throws JsonException
     */
    #[Post(path: '/admin/api/v1/posts')]
    #[RequiresPermission(permission: 'posts.create')]
    public function store(Request $request): Response
    {
        $data = json_decode($request->body(), true, flags: JSON_THROW_ON_ERROR);

        // Validate and save...

        return ApiResponse::created(data: ['id' => 1, 'title' => $data['title']]);
    }

    #[Delete(path: '/admin/api/v1/posts/{id}')]
    #[RequiresPermission(permission: 'posts.delete')]
    public function destroy(int $id): Response
    {
        // Delete the post...

        return ApiResponse::success();
    }
}
```

The `ApiResponse` class provides these factory methods:

| Method | Status Code | Use Case |
|---|---|---|
| `ApiResponse::success()` | 200 | Successful read/update |
| `ApiResponse::created()` | 201 | Resource created |
| `ApiResponse::paginated()` | 200 | Paginated list responses |

For errors, throw an `HttpException` instead (for example `throw HttpException::notFound('Post not found.');`). The routing pipeline renders it as `{"message": ...}`, the same shape the admin auth middleware uses for its `401` and `403` responses. See [Errors and HTTP Exceptions](/docs/packages/routing/#errors-and-http-exceptions).

## Step 11: Manage Roles and Permissions

Use the repository interfaces to manage roles and their permissions:

```php
use Marko\AdminAuth\Entity\Role;
use Marko\AdminAuth\Repository\RoleRepositoryInterface;
use Marko\AdminAuth\Repository\PermissionRepositoryInterface;
use Marko\AdminAuth\Repository\AdminUserRepositoryInterface;

// Create a new role
$role = new Role();
$role->name = 'Editor';
$role->slug = 'editor';
$role->description = 'Can manage posts but not users';
$role->isSuperAdmin = '0';
$roleRepository->save($role);

// Assign permissions to the role
$viewPermission = $permissionRepository->findByKey('posts.view');
$editPermission = $permissionRepository->findByKey('posts.edit');

$roleRepository->syncPermissions(
    roleId: $role->id,
    permissionIds: [$viewPermission->id, $editPermission->id],
);

// Assign the role to an admin user
$adminUserRepository->syncRoles(
    userId: $adminUser->id,
    roleIds: [$role->id],
);
```

Check a user's roles and permissions:

```php
use Marko\AdminAuth\Entity\AdminUserInterface;

// Check a specific permission
$canEdit = $adminUser->hasPermission('posts.edit');

// Check a specific role
$isEditor = $adminUser->hasRole('editor');

// Get all permission keys
$permissions = $adminUser->getPermissionKeys();

// Get all roles
$roles = $adminUser->getRoles();
```

The `PermissionRegistry` also supports wildcard matching --- a user with the permission key `posts.*` matches any permission starting with `posts.`:

```php
use Marko\AdminAuth\Contracts\PermissionRegistryInterface;

// Check if a pattern matches a specific key
$permissionRegistry->matches('posts.*', 'posts.edit');   // true
$permissionRegistry->matches('posts.*', 'users.view');   // false
$permissionRegistry->matches('*', 'anything');            // true
```

## Step 12: Create Dashboard Widgets

Implement the `DashboardWidgetInterface` to add custom widgets to your admin dashboard:

```php title="app/admin/src/Widget/RecentPostsWidget.php"
<?php

declare(strict_types=1);

namespace App\Admin\Widget;

use Marko\Admin\Contracts\DashboardWidgetInterface;
use Marko\View\ViewInterface;

readonly class RecentPostsWidget implements DashboardWidgetInterface
{
    public function __construct(
        private ViewInterface $view,
    ) {}

    public function getId(): string
    {
        return 'recent-posts';
    }

    public function getLabel(): string
    {
        return 'Recent Posts';
    }

    public function getSortOrder(): int
    {
        return 10;
    }

    public function render(): string
    {
        $posts = []; // Fetch recent posts from your repository

        return $this->view->renderToString('admin::widget/recent-posts', [
            'posts' => $posts,
        ]);
    }
}
```

With a corresponding Latte template:

```latte title="app/admin/resources/views/widget/recent-posts.latte"
<div class="widget">
    <h3>Recent Posts</h3>
    <ul n:if="$posts">
        {foreach $posts as $post}
            <li>
                <a href="/admin/posts/{$post->getId()}">{$post->getTitle()}</a>
            </li>
        {/foreach}
    </ul>
    <p n:if="!$posts">No recent posts.</p>
</div>
```

## What You've Learned

- Installing and configuring the admin package stack (`marko/admin`, `marko/admin-panel`, `marko/admin-auth`, `marko/admin-api`)
- Setting up admin authentication on the separate [admin guard](/docs/packages/admin-auth/#the-admin-guard)
- Creating roles, permissions, and assigning them to admin users
- Building admin sections with [`#[AdminSection]`](/docs/packages/admin/) and [`MenuItem`](/docs/packages/admin/) for sidebar navigation
- Protecting controllers with [`AdminAuthMiddleware`](/docs/packages/admin-auth/) and [`#[RequiresPermission]`](/docs/packages/admin-auth/)
- Using the [`AdminMenuBuilder`](/docs/packages/admin-panel/) to render permission-filtered navigation
- Building admin API endpoints with [`ApiResponse`](/docs/packages/admin-api/)
- Managing roles and permissions with the [repository interfaces](/docs/packages/admin-auth/)
- Creating dashboard widgets with [`DashboardWidgetInterface`](/docs/packages/admin/)

## Next Steps

- [Build a REST API](/docs/tutorials/build-a-rest-api/) --- create a public-facing JSON API alongside your admin panel
- [Build a Blog](/docs/tutorials/build-a-blog/) --- add a frontend to complement your admin CRUD
- [Create a Custom Module](/docs/tutorials/custom-module/) --- package your admin section as a reusable Composer module
