<?php

declare(strict_types=1);

namespace Marko\AdminApi\Tests\Feature;

use Marko\Admin\AdminSectionRegistry;
use Marko\Admin\Config\AdminConfigInterface;
use Marko\Admin\Discovery\AdminSectionCacheContributor;
use Marko\AdminApi\Controller\SectionController;
use Marko\AdminApi\Tests\Fixtures\FixedAdminGuardResolver;
use Marko\AdminAuth\AdminGuardResolver;
use Marko\AdminAuth\Contracts\PermissionRegistryInterface;
use Marko\AdminAuth\Entity\AdminUser;
use Marko\AdminAuth\Entity\Role;
use Marko\AdminAuth\Middleware\AdminAuthMiddleware;
use Marko\AdminAuth\PermissionRegistry;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Core\Commands\DiscoveryCacheCommand;
use Marko\Core\Container\BindingRegistry;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Discovery\CachedDiscovery;
use Marko\Core\Discovery\DiscoveryCache;
use Marko\Core\Discovery\DiscoveryCompiler;
use Marko\Core\Discovery\DiscoveryEnvironment;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepository;
use Marko\Core\Module\ModuleRepositoryInterface;
use Marko\Core\Path\ProjectPaths;
use Marko\Routing\Http\Request;
use Marko\Routing\RouteCollection;
use Marko\Routing\RouteDefinition;
use Marko\Routing\RouteMatcher;
use Marko\Routing\Router;
use Marko\Testing\Fake\FakeGuard;
use ReflectionClass;

/*
 * Sections and permissions declared only by attribute reach the admin API with
 * no manual registration: marko/admin's and marko/admin-auth's real module.php
 * boot callbacks discover them, as Application does after wiring every module (#314).
 */

readonly class SectionBootAdminConfig implements AdminConfigInterface
{
    public function getRoutePrefix(): string
    {
        return '/admin';
    }

    public function getName(): string
    {
        return 'Admin';
    }
}

/**
 * The real module.php of the package that declares the given class, as a manifest.
 */
function sectionBootPackageModule(
    string $name,
    string $classInPackage,
): ModuleManifest {
    $path = dirname((string) new ReflectionClass($classInPackage)->getFileName(), 2);
    $module = require $path . '/module.php';

    return new ModuleManifest(
        name: $name,
        version: '1.0.0',
        bindings: $module['bindings'] ?? [],
        singletons: $module['singletons'] ?? [],
        path: $path,
        boot: $module['boot'] ?? null,
        discovery: $module['discovery'] ?? [],
    );
}

/**
 * A fresh app module whose src/ declares one section, only by attribute.
 */
function sectionBootAppModule(): ModuleManifest
{
    $path = sys_get_temp_dir() . '/marko-admin-section-boot-' . bin2hex(random_bytes(8));
    mkdir($path . '/src', 0755, true);
    file_put_contents($path . '/src/WarehouseSection.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Warehouse;

use Marko\Admin\Attributes\AdminPermission;
use Marko\Admin\Attributes\AdminSection;
use Marko\Admin\Contracts\AdminSectionInterface;
use Marko\Admin\MenuItem;

#[AdminSection(id: 'warehouse', label: 'Warehouse', icon: 'truck', sortOrder: 40)]
#[AdminPermission(id: 'warehouse.stock.view', label: 'View Stock')]
class WarehouseSection implements AdminSectionInterface
{
    public function getId(): string { return 'warehouse'; }
    public function getLabel(): string { return 'Warehouse'; }
    public function getIcon(): string { return 'truck'; }
    public function getSortOrder(): int { return 40; }

    public function getMenuItems(): array
    {
        return [
            new MenuItem(
                id: 'stock',
                label: 'Stock',
                url: '/admin/warehouse/stock',
                permission: 'warehouse.stock.view',
            ),
        ];
    }
}
PHP);

    return new ModuleManifest(
        name: 'app/warehouse',
        version: '1.0.0',
        path: $path,
    );
}

function removeSectionBootDirectory(
    string $path,
): void {
    if (!is_dir($path)) {
        return;
    }

    foreach (scandir($path) as $item) {
        if (
            $item === '.'
            || $item === '..'
        ) {
            continue;
        }

        $child = "$path/$item";
        is_dir($child) ? removeSectionBootDirectory($child) : unlink($child);
    }

    rmdir($path);
}

function sectionBootWarehouseUser(): AdminUser
{
    $role = new Role();
    $role->id = 1;
    $role->name = 'Warehouse Manager';
    $role->slug = 'warehouse-manager';

    $user = new AdminUser();
    $user->id = 1;
    $user->email = 'admin@example.com';
    $user->password = 'hashed';
    $user->name = 'Admin User';
    $user->setRoles(roles: [$role], permissionKeys: ['warehouse.stock.view']);

    return $user;
}

it('returns a section declared only by attribute from GET /admin/api/v1/sections', function (): void {
    $appModule = sectionBootAppModule();
    $modules = [
        sectionBootPackageModule('marko/admin', AdminSectionRegistry::class),
        sectionBootPackageModule('marko/admin-auth', PermissionRegistry::class),
        $appModule,
    ];

    $guard = new FakeGuard(name: 'admin');
    $guard->setUser(sectionBootWarehouseUser());

    $container = new Container();
    $container->instance(ContainerInterface::class, $container);
    $container->instance(AdminGuardResolver::class, new FixedAdminGuardResolver($guard));
    $container->instance(AdminConfigInterface::class, new SectionBootAdminConfig());
    $container->instance(CachedDiscovery::class, new CachedDiscovery());
    $container->instance(ModuleRepositoryInterface::class, new ModuleRepository($modules));

    $bindingRegistry = new BindingRegistry($container);

    foreach ($modules as $module) {
        $bindingRegistry->registerModule($module);
    }

    try {
        foreach ($modules as $module) {
            if ($module->boot !== null) {
                $container->call($module->boot);
            }
        }
    } finally {
        removeSectionBootDirectory($appModule->path);
    }

    $routes = new RouteCollection();
    $routes->add(new RouteDefinition(
        method: 'GET',
        path: '/admin/api/v1/sections',
        controller: SectionController::class,
        action: 'index',
        middleware: [AdminAuthMiddleware::class],
    ));
    $router = new Router(matcher: new RouteMatcher($routes), container: $container);

    $response = $router->handle(new Request(server: [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/admin/api/v1/sections',
        'HTTP_ACCEPT' => 'application/json',
    ]));

    $permissionKeys = array_map(
        static fn ($permission): string => $permission->key,
        $container->get(PermissionRegistryInterface::class)->all(),
    );

    expect($response->statusCode())->toBe(200)
        ->and(json_decode($response->body(), true)['data'])->toBe([
            ['id' => 'warehouse', 'label' => 'Warehouse', 'icon' => 'truck', 'sort_order' => 40],
        ])
        ->and($permissionKeys)->toBe(['warehouse.stock.view']);
})->issue(314);

it('lists the admin sections section in the discovery:cache output', function (): void {
    $project = sys_get_temp_dir() . '/marko-admin-section-cache-' . bin2hex(random_bytes(8));
    mkdir($project, 0755, true);
    $appModule = sectionBootAppModule();
    $modules = [sectionBootPackageModule('marko/admin', AdminSectionRegistry::class), $appModule];

    $command = new DiscoveryCacheCommand(
        new DiscoveryCompiler(new Container()),
        new DiscoveryCache(new ProjectPaths($project), new DiscoveryEnvironment()),
        new ModuleRepository($modules),
    );
    $stream = fopen('php://memory', 'r+');

    try {
        $exitCode = $command->execute(new Input([]), new Output($stream));
        $payload = require new DiscoveryCache(new ProjectPaths($project), new DiscoveryEnvironment())->path();
    } finally {
        removeSectionBootDirectory($appModule->path);
        removeSectionBootDirectory($project);
    }

    rewind($stream);

    expect($exitCode)->toBe(0)
        ->and((string) stream_get_contents($stream))->toContain(AdminSectionCacheContributor::KEY . ' (1)')
        ->and($payload['sections'][AdminSectionCacheContributor::KEY][0]['className'])
        ->toBe('App\\Warehouse\\WarehouseSection');
})->issue(314);
