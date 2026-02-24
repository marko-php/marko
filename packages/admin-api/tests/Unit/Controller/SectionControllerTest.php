<?php

declare(strict_types=1);

namespace Marko\AdminApi\Tests\Unit\Controller;

use Marko\Admin\AdminSectionRegistry;
use Marko\Admin\Contracts\AdminSectionInterface;
use Marko\Admin\MenuItem;
use Marko\AdminApi\Controller\SectionController;
use Marko\AdminAuth\Entity\AdminUser;
use Marko\AdminAuth\Entity\Role;
use Marko\AdminAuth\Middleware\AdminAuthMiddleware;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Http\Response;
use Marko\Testing\Fake\FakeGuard;
use ReflectionClass;
use ReflectionMethod;

function createTestSection(
    string $id,
    string $label,
    string $icon,
    int $sortOrder,
    array $menuItems = [],
): AdminSectionInterface {
    return new class ($id, $label, $icon, $sortOrder, $menuItems) implements AdminSectionInterface
    {
        public function __construct(
            private readonly string $id,
            private readonly string $label,
            private readonly string $icon,
            private readonly int $sortOrder,
            private readonly array $menuItems,
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
    };
}

function createTestAdminUser(
    array $roles = [],
    array $permissionKeys = [],
): AdminUser {
    $user = new AdminUser();
    $user->id = 1;
    $user->email = 'admin@example.com';
    $user->password = 'hashed';
    $user->name = 'Admin User';
    $user->setRoles(roles: $roles, permissionKeys: $permissionKeys);

    return $user;
}

it('returns list of admin sections on GET /admin/api/v1/sections', function (): void {
    $registry = new AdminSectionRegistry();
    $registry->register(createTestSection('catalog', 'Catalog', 'box', 10));
    $registry->register(createTestSection('sales', 'Sales', 'cart', 20));

    $guard = new FakeGuard(name: 'admin-api', attemptResult: false);
    $superAdminRole = new Role();
    $superAdminRole->id = 1;
    $superAdminRole->name = 'Super Admin';
    $superAdminRole->slug = 'super-admin';
    $superAdminRole->isSuperAdmin = '1';
    $guard->setUser(createTestAdminUser(roles: [$superAdminRole]));

    $controller = new SectionController(
        sectionRegistry: $registry,
        guard: $guard,
    );

    $response = $controller->index();
    $body = json_decode($response->body(), true);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->statusCode())->toBe(200)
        ->and($response->headers()['Content-Type'])->toBe('application/json')
        ->and($body)->toHaveKey('data')
        ->and($body)->toHaveKey('meta')
        ->and($body['data'])->toHaveCount(2)
        ->and($body['data'][0]['id'])->toBe('catalog')
        ->and($body['data'][0]['label'])->toBe('Catalog')
        ->and($body['data'][0]['icon'])->toBe('box')
        ->and($body['data'][0]['sort_order'])->toBe(10)
        ->and($body['data'][1]['id'])->toBe('sales')
        ->and($body['data'][1]['label'])->toBe('Sales')
        ->and($body['data'][1]['icon'])->toBe('cart')
        ->and($body['data'][1]['sort_order'])->toBe(20);
});

it('filters sections by user permissions', function (): void {
    $registry = new AdminSectionRegistry();

    // Catalog section with menu items requiring catalog.* permissions
    $registry->register(createTestSection('catalog', 'Catalog', 'box', 10, [
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
    ]));

    // Sales section with menu items requiring sales.* permissions
    $registry->register(createTestSection('sales', 'Sales', 'cart', 20, [
        new MenuItem(id: 'orders', label: 'Orders', url: '/admin/sales/orders', permission: 'sales.orders.view'),
    ]));

    // System section with menu items requiring system.* permissions
    $registry->register(createTestSection('system', 'System', 'cog', 30, [
        new MenuItem(
            id: 'config',
            label: 'Configuration',
            url: '/admin/system/config',
            permission: 'system.config.view',
        ),
    ]));

    $guard = new FakeGuard(name: 'admin-api', attemptResult: false);
    $editorRole = new Role();
    $editorRole->id = 2;
    $editorRole->name = 'Editor';
    $editorRole->slug = 'editor';

    // User only has catalog permissions, not sales or system
    $guard->setUser(createTestAdminUser(
        roles: [$editorRole],
        permissionKeys: ['catalog.products.view', 'catalog.categories.view'],
    ));

    $controller = new SectionController(
        sectionRegistry: $registry,
        guard: $guard,
    );

    $response = $controller->index();
    $body = json_decode($response->body(), true);

    // Should only see catalog section since user only has catalog permissions
    expect($body['data'])->toHaveCount(1)
        ->and($body['data'][0]['id'])->toBe('catalog');
});

it('returns section detail with menu items on GET /admin/api/v1/sections/{id}', function (): void {
    $registry = new AdminSectionRegistry();
    $registry->register(createTestSection('catalog', 'Catalog', 'box', 10, [
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
    ]));

    $guard = new FakeGuard(name: 'admin-api', attemptResult: false);
    $superAdminRole = new Role();
    $superAdminRole->id = 1;
    $superAdminRole->name = 'Super Admin';
    $superAdminRole->slug = 'super-admin';
    $superAdminRole->isSuperAdmin = '1';
    $guard->setUser(createTestAdminUser(roles: [$superAdminRole]));

    $controller = new SectionController(
        sectionRegistry: $registry,
        guard: $guard,
    );

    $response = $controller->show('catalog');
    $body = json_decode($response->body(), true);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->statusCode())->toBe(200)
        ->and($response->headers()['Content-Type'])->toBe('application/json')
        ->and($body)->toHaveKey('data')
        ->and($body['data']['id'])->toBe('catalog')
        ->and($body['data']['label'])->toBe('Catalog')
        ->and($body['data']['icon'])->toBe('box')
        ->and($body['data']['sort_order'])->toBe(10)
        ->and($body['data']['menu_items'])->toHaveCount(2)
        ->and($body['data']['menu_items'][0]['id'])->toBe('products')
        ->and($body['data']['menu_items'][0]['label'])->toBe('Products')
        ->and($body['data']['menu_items'][0]['url'])->toBe('/admin/catalog/products')
        ->and($body['data']['menu_items'][0]['icon'])->toBe('package')
        ->and($body['data']['menu_items'][0]['sort_order'])->toBe(10)
        ->and($body['data']['menu_items'][0]['permission'])->toBe('catalog.products.view')
        ->and($body['data']['menu_items'][1]['id'])->toBe('categories')
        ->and($body['data']['menu_items'][1]['label'])->toBe('Categories');
});

it('returns 404 when section not found', function (): void {
    $registry = new AdminSectionRegistry();

    $guard = new FakeGuard(name: 'admin-api', attemptResult: false);
    $superAdminRole = new Role();
    $superAdminRole->id = 1;
    $superAdminRole->name = 'Super Admin';
    $superAdminRole->slug = 'super-admin';
    $superAdminRole->isSuperAdmin = '1';
    $guard->setUser(createTestAdminUser(roles: [$superAdminRole]));

    $controller = new SectionController(
        sectionRegistry: $registry,
        guard: $guard,
    );

    $response = $controller->show('nonexistent');
    $body = json_decode($response->body(), true);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->statusCode())->toBe(404)
        ->and($response->headers()['Content-Type'])->toBe('application/json')
        ->and($body)->toHaveKey('errors')
        ->and($body['errors'][0]['message'])->toBe("Section 'nonexistent' not found");
});

it('uses ApiResponse format for all responses', function (): void {
    $registry = new AdminSectionRegistry();
    $registry->register(createTestSection('catalog', 'Catalog', 'box', 10));

    $guard = new FakeGuard(name: 'admin-api', attemptResult: false);
    $superAdminRole = new Role();
    $superAdminRole->id = 1;
    $superAdminRole->name = 'Super Admin';
    $superAdminRole->slug = 'super-admin';
    $superAdminRole->isSuperAdmin = '1';
    $guard->setUser(createTestAdminUser(roles: [$superAdminRole]));

    $controller = new SectionController(
        sectionRegistry: $registry,
        guard: $guard,
    );

    // Index response has data and meta keys
    $indexBody = json_decode($controller->index()->body(), true);

    // Show response has data and meta keys
    $showBody = json_decode($controller->show('catalog')->body(), true);

    // Not found response has errors key
    $notFoundBody = json_decode($controller->show('nonexistent')->body(), true);

    expect($indexBody)->toHaveKey('data')
        ->and($indexBody)->toHaveKey('meta')
        ->and($showBody)->toHaveKey('data')
        ->and($showBody)->toHaveKey('meta')
        ->and($notFoundBody)->toHaveKey('errors');
});

it('applies AdminAuthMiddleware to all routes', function (): void {
    $reflection = new ReflectionClass(SectionController::class);

    // Check class-level Middleware attribute
    $middlewareAttributes = $reflection->getAttributes(Middleware::class);

    // Verify route attributes exist on methods
    $indexRouteAttributes = (new ReflectionMethod(SectionController::class, 'index'))->getAttributes(Get::class);
    $showRouteAttributes = (new ReflectionMethod(SectionController::class, 'show'))->getAttributes(Get::class);

    expect($middlewareAttributes)->toHaveCount(1)
        ->and($middlewareAttributes[0]->newInstance()->middleware)->toContain(AdminAuthMiddleware::class)
        ->and($indexRouteAttributes)->toHaveCount(1)
        ->and($indexRouteAttributes[0]->newInstance()->path)->toBe('/admin/api/v1/sections')
        ->and($showRouteAttributes)->toHaveCount(1)
        ->and($showRouteAttributes[0]->newInstance()->path)->toBe('/admin/api/v1/sections/{id}');
});
