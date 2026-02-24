<?php

declare(strict_types=1);

namespace Marko\AdminPanel\Tests\Unit\Controller;

use Marko\Admin\Contracts\AdminSectionInterface;
use Marko\Admin\Contracts\AdminSectionRegistryInterface;
use Marko\AdminAuth\Middleware\AdminAuthMiddleware;
use Marko\AdminPanel\Controller\DashboardController;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Testing\Fake\FakeAuthenticatable;
use Marko\Testing\Fake\FakeGuard;
use Marko\View\ViewInterface;
use ReflectionMethod;

// Stub for ViewInterface
class StubView implements ViewInterface
{
    public string $lastTemplate = '';

    /** @var array<string, mixed> */
    public array $lastData = [];

    public function render(
        string $template,
        array $data = [],
    ): Response {
        $this->lastTemplate = $template;
        $this->lastData = $data;

        return Response::html('<html>rendered</html>');
    }

    public function renderToString(
        string $template,
        array $data = [],
    ): string {
        $this->lastTemplate = $template;
        $this->lastData = $data;

        return '<html>rendered</html>';
    }
}

// Stub for AdminSectionRegistryInterface
class StubSectionRegistry implements AdminSectionRegistryInterface
{
    /** @var array<AdminSectionInterface> */
    private array $sections = [];

    public function register(
        AdminSectionInterface $section,
    ): void {
        $this->sections[$section->getId()] = $section;
    }

    public function all(): array
    {
        return array_values($this->sections);
    }

    public function get(
        string $id,
    ): AdminSectionInterface {
        return $this->sections[$id];
    }
}

// Stub for AdminSectionInterface
class StubAdminSection implements AdminSectionInterface
{
    public function __construct(
        private readonly string $id,
        private readonly string $label,
        private readonly string $icon = 'default',
        private readonly int $sortOrder = 0,
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
        return [];
    }
}

it('requires authentication via AdminAuthMiddleware for dashboard', function (): void {
    $middlewareAttributes = (new ReflectionMethod(DashboardController::class, 'index'))->getAttributes(Middleware::class);

    expect($middlewareAttributes)->toHaveCount(1)
        ->and($middlewareAttributes[0]->newInstance()->middleware)->toContain(AdminAuthMiddleware::class);
});

it('renders dashboard template with registered sections on GET /admin', function (): void {
    $view = new StubView();
    $registry = new StubSectionRegistry();
    $guard = new FakeGuard(name: 'admin', attemptResult: false);

    $section1 = new StubAdminSection(id: 'catalog', label: 'Catalog');
    $section2 = new StubAdminSection(id: 'content', label: 'Content');
    $registry->register($section1);
    $registry->register($section2);

    $user = new FakeAuthenticatable(id: 1);
    $guard->setUser($user);

    $controller = new DashboardController(
        view: $view,
        sectionRegistry: $registry,
        guard: $guard,
    );

    $request = new Request();
    $response = $controller->index($request);

    expect($response->statusCode())->toBe(200)
        ->and($response->headers())->toHaveKey('Content-Type')
        ->and($response->headers()['Content-Type'])->toContain('text/html')
        ->and($view->lastTemplate)->toBe('admin-panel::dashboard/index')
        ->and($view->lastData)->toHaveKey('sections')
        ->and($view->lastData['sections'])->toHaveCount(2);
});

it('passes admin sections to dashboard template for display', function (): void {
    $view = new StubView();
    $registry = new StubSectionRegistry();
    $guard = new FakeGuard(name: 'admin', attemptResult: false);

    $section1 = new StubAdminSection(id: 'catalog', label: 'Catalog', sortOrder: 10);
    $section2 = new StubAdminSection(id: 'content', label: 'Content', sortOrder: 20);
    $section3 = new StubAdminSection(id: 'settings', label: 'Settings', sortOrder: 30);
    $registry->register($section1);
    $registry->register($section2);
    $registry->register($section3);

    $user = new FakeAuthenticatable(id: 1);
    $guard->setUser($user);

    $controller = new DashboardController(
        view: $view,
        sectionRegistry: $registry,
        guard: $guard,
    );

    $request = new Request();
    $controller->index($request);

    $sections = $view->lastData['sections'];

    expect($sections)->toHaveCount(3)
        ->and($sections[0]->getId())->toBe('catalog')
        ->and($sections[0]->getLabel())->toBe('Catalog')
        ->and($sections[1]->getId())->toBe('content')
        ->and($sections[2]->getId())->toBe('settings');
});

it('passes current user to base layout template', function (): void {
    $view = new StubView();
    $registry = new StubSectionRegistry();
    $guard = new FakeGuard(name: 'admin', attemptResult: false);

    $user = new FakeAuthenticatable(id: 5);
    $guard->setUser($user);

    $controller = new DashboardController(
        view: $view,
        sectionRegistry: $registry,
        guard: $guard,
    );

    $request = new Request();
    $controller->index($request);

    expect($view->lastData)->toHaveKey('currentUser')
        ->and($view->lastData['currentUser'])->toBe($user)
        ->and($view->lastData['currentUser']->getAuthIdentifier())->toBe(5);
});
