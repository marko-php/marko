<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Unit\Admin\Controllers;

use Closure;
use Marko\AdminAuth\Attributes\RequiresPermission;
use Marko\AdminAuth\Middleware\AdminAuthMiddleware;
use Marko\Blog\Admin\Controllers\CategoryAdminController;
use Marko\Blog\Dto\PaginatedResult;
use Marko\Blog\Entity\Category;
use Marko\Blog\Events\Category\CategoryCreated;
use Marko\Blog\Events\Category\CategoryDeleted;
use Marko\Blog\Events\Category\CategoryUpdated;
use Marko\Blog\Repositories\CategoryRepositoryInterface;
use Marko\Blog\Services\PaginationServiceInterface;
use Marko\Blog\Services\SlugGeneratorInterface;
use Marko\Core\Event\Event;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Database\Entity\Entity;
use Marko\Database\Exceptions\RepositoryException;
use Marko\Routing\Attributes\Delete;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Attributes\Post as PostRoute;
use Marko\Routing\Attributes\Put;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\View\ViewInterface;
use ReflectionClass;

it('creates CategoryAdminController with list, create, store, edit, update, delete actions', function (): void {
    $reflection = new ReflectionClass(CategoryAdminController::class);

    expect($reflection->hasMethod('index'))->toBeTrue()
        ->and($reflection->hasMethod('create'))->toBeTrue()
        ->and($reflection->hasMethod('store'))->toBeTrue()
        ->and($reflection->hasMethod('edit'))->toBeTrue()
        ->and($reflection->hasMethod('update'))->toBeTrue()
        ->and($reflection->hasMethod('destroy'))->toBeTrue();
});

it('requires blog.categories.view permission for category list', function (): void {
    $reflection = new ReflectionClass(CategoryAdminController::class);
    $method = $reflection->getMethod('index');

    // Check route attribute
    $routeAttrs = $method->getAttributes(Get::class);
    expect($routeAttrs)->toHaveCount(1);
    $route = $routeAttrs[0]->newInstance();
    expect($route->path)->toBe('/admin/blog/categories');

    // Check permission attribute
    $permAttrs = $method->getAttributes(RequiresPermission::class);
    expect($permAttrs)->toHaveCount(1);
    $perm = $permAttrs[0]->newInstance();
    expect($perm->permission)->toBe('blog.categories.view');

    // Test actual behavior - returns paginated categories
    $categories = [
        createTestCategoryEntity(1, 'Tech', 'tech'),
        createTestCategoryEntity(2, 'Science', 'science'),
    ];
    $capturedData = [];
    $controller = createCategoryController(
        categories: $categories,
        totalCategories: 2,
        capturedData: $capturedData,
    );

    $request = new Request(query: ['page' => '1']);
    $response = $controller->index($request);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->statusCode())->toBe(200)
        ->and($response->body())->toContain('blog::admin/category/index')
        ->and($capturedData)->toHaveKey('categories')
        ->and($capturedData['categories'])->toBeInstanceOf(PaginatedResult::class)
        ->and($capturedData['categories']->items)->toHaveCount(2);
});

it('supports parent category selection in category create', function (): void {
    $reflection = new ReflectionClass(CategoryAdminController::class);
    $method = $reflection->getMethod('create');

    $routeAttrs = $method->getAttributes(Get::class);
    expect($routeAttrs)->toHaveCount(1);
    $route = $routeAttrs[0]->newInstance();
    expect($route->path)->toBe('/admin/blog/categories/create');

    $permAttrs = $method->getAttributes(RequiresPermission::class);
    expect($permAttrs)->toHaveCount(1);
    $perm = $permAttrs[0]->newInstance();
    expect($perm->permission)->toBe('blog.categories.create');

    // Create form should include all categories for parent selection
    $existingCategories = [
        createTestCategoryEntity(1, 'Parent Category', 'parent-category'),
    ];
    $capturedData = [];
    $controller = createCategoryController(
        categories: $existingCategories,
        capturedData: $capturedData,
    );

    $response = $controller->create();

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->statusCode())->toBe(200)
        ->and($response->body())->toContain('blog::admin/category/create')
        ->and($capturedData)->toHaveKey('categories')
        ->and($capturedData['categories'])->toHaveCount(1);
});

it('creates new category on POST /admin/blog/categories with valid data', function (): void {
    $reflection = new ReflectionClass(CategoryAdminController::class);
    $method = $reflection->getMethod('store');

    $routeAttrs = $method->getAttributes(PostRoute::class);
    expect($routeAttrs)->toHaveCount(1);
    $route = $routeAttrs[0]->newInstance();
    expect($route->path)->toBe('/admin/blog/categories');

    $permAttrs = $method->getAttributes(RequiresPermission::class);
    expect($permAttrs)->toHaveCount(1);
    $perm = $permAttrs[0]->newInstance();
    expect($perm->permission)->toBe('blog.categories.create');

    $savedEntities = [];
    $capturedData = [];
    $controller = createCategoryController(
        capturedData: $capturedData,
        savedEntities: $savedEntities,
    );

    $request = new Request(post: [
        'name' => 'New Category',
        'parent_id' => '5',
    ]);

    $response = $controller->store($request);

    expect($response->statusCode())->toBe(302)
        ->and($response->headers())->toHaveKey('Location')
        ->and($savedEntities)->toHaveCount(1)
        ->and($savedEntities[0])->toBeInstanceOf(Category::class)
        ->and($savedEntities[0]->name)->toBe('New Category')
        ->and($savedEntities[0]->parentId)->toBe(5);
});

it('creates category without parent when parent_id is empty', function (): void {
    $savedEntities = [];
    $controller = createCategoryController(
        savedEntities: $savedEntities,
    );

    $request = new Request(post: [
        'name' => 'Root Category',
        'parent_id' => '',
    ]);

    $response = $controller->store($request);

    expect($response->statusCode())->toBe(302)
        ->and($savedEntities)->toHaveCount(1)
        ->and($savedEntities[0]->parentId)->toBeNull();
});

it('returns validation errors on POST /admin/blog/categories with invalid data', function (): void {
    $savedEntities = [];
    $capturedData = [];
    $controller = createCategoryController(
        capturedData: $capturedData,
        savedEntities: $savedEntities,
    );

    $request = new Request(post: [
        'name' => '',
    ]);

    $response = $controller->store($request);

    expect($response->statusCode())->toBe(200)
        ->and($response->body())->toContain('blog::admin/category/create')
        ->and($capturedData)->toHaveKey('errors')
        ->and($capturedData['errors'])->toContain('Name is required')
        ->and($savedEntities)->toHaveCount(0);
});

it('supports parent category selection in category edit', function (): void {
    $reflection = new ReflectionClass(CategoryAdminController::class);
    $method = $reflection->getMethod('edit');

    $routeAttrs = $method->getAttributes(Get::class);
    expect($routeAttrs)->toHaveCount(1);
    $route = $routeAttrs[0]->newInstance();
    expect($route->path)->toBe('/admin/blog/categories/{id}/edit');

    $permAttrs = $method->getAttributes(RequiresPermission::class);
    expect($permAttrs)->toHaveCount(1);
    $perm = $permAttrs[0]->newInstance();
    expect($perm->permission)->toBe('blog.categories.edit');

    $category = createTestCategoryEntity(1, 'Tech', 'tech', parentId: 5);
    $allCategories = [
        createTestCategoryEntity(5, 'Parent', 'parent'),
        $category,
    ];
    $capturedData = [];
    $controller = createCategoryController(
        categories: $allCategories,
        findCategory: $category,
        capturedData: $capturedData,
    );

    $response = $controller->edit(1);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->statusCode())->toBe(200)
        ->and($response->body())->toContain('blog::admin/category/edit')
        ->and($capturedData)->toHaveKey('category')
        ->and($capturedData['category']->getName())->toBe('Tech')
        ->and($capturedData)->toHaveKey('categories');
});

it('returns 404 when editing non-existent category', function (): void {
    $controller = createCategoryController();

    $response = $controller->edit(999);

    expect($response->statusCode())->toBe(404)
        ->and($response->body())->toContain('not found');
});

it('updates category on PUT /admin/blog/categories/{id} with valid data', function (): void {
    $reflection = new ReflectionClass(CategoryAdminController::class);
    $method = $reflection->getMethod('update');

    $routeAttrs = $method->getAttributes(Put::class);
    expect($routeAttrs)->toHaveCount(1);
    $route = $routeAttrs[0]->newInstance();
    expect($route->path)->toBe('/admin/blog/categories/{id}');

    $permAttrs = $method->getAttributes(RequiresPermission::class);
    expect($permAttrs)->toHaveCount(1);
    $perm = $permAttrs[0]->newInstance();
    expect($perm->permission)->toBe('blog.categories.edit');

    $existingCategory = createTestCategoryEntity(1, 'Old Name', 'old-name');
    $savedEntities = [];
    $controller = createCategoryController(
        findCategory: $existingCategory,
        savedEntities: $savedEntities,
    );

    $request = new Request(post: [
        'name' => 'Updated Name',
        'parent_id' => '3',
    ]);

    $response = $controller->update(1, $request);

    expect($response->statusCode())->toBe(302)
        ->and($response->headers())->toHaveKey('Location')
        ->and($savedEntities)->toHaveCount(1)
        ->and($savedEntities[0]->name)->toBe('Updated Name')
        ->and($savedEntities[0]->parentId)->toBe(3);
});

it('deletes category on DELETE /admin/blog/categories/{id} with blog.categories.delete permission', function (): void {
    $reflection = new ReflectionClass(CategoryAdminController::class);
    $method = $reflection->getMethod('destroy');

    $routeAttrs = $method->getAttributes(Delete::class);
    expect($routeAttrs)->toHaveCount(1);
    $route = $routeAttrs[0]->newInstance();
    expect($route->path)->toBe('/admin/blog/categories/{id}');

    $permAttrs = $method->getAttributes(RequiresPermission::class);
    expect($permAttrs)->toHaveCount(1);
    $perm = $permAttrs[0]->newInstance();
    expect($perm->permission)->toBe('blog.categories.delete');

    $existingCategory = createTestCategoryEntity(1, 'Category to Delete', 'category-to-delete');
    $deletedEntities = [];
    $controller = createCategoryController(
        findCategory: $existingCategory,
        deletedEntities: $deletedEntities,
    );

    $response = $controller->destroy(1);

    expect($response->statusCode())->toBe(302)
        ->and($response->headers())->toHaveKey('Location')
        ->and($deletedEntities)->toHaveCount(1)
        ->and($deletedEntities[0]->id)->toBe(1);
});

it('applies AdminAuthMiddleware to CategoryAdminController', function (): void {
    $reflection = new ReflectionClass(CategoryAdminController::class);

    $middlewareAttrs = $reflection->getAttributes(Middleware::class);
    expect($middlewareAttrs)->toHaveCount(1);

    $middleware = $middlewareAttrs[0]->newInstance();
    expect($middleware->middleware)->toContain(AdminAuthMiddleware::class);
});

it('dispatches CategoryCreated, CategoryUpdated, and CategoryDeleted events', function (): void {
    // Test CategoryCreated on store
    $dispatchedEvents = [];
    $savedEntities = [];
    $controller = createCategoryController(
        savedEntities: $savedEntities,
        dispatchedEvents: $dispatchedEvents,
    );

    $request = new Request(post: [
        'name' => 'New Category',
    ]);

    $controller->store($request);

    expect($dispatchedEvents)->toHaveCount(1)
        ->and($dispatchedEvents[0])->toBeInstanceOf(CategoryCreated::class)
        ->and($dispatchedEvents[0]->getCategory()->getName())->toBe('New Category');

    // Test CategoryUpdated on update
    $existingCategory = createTestCategoryEntity(1, 'Existing', 'existing');
    $dispatchedEvents2 = [];
    $savedEntities2 = [];
    $controller2 = createCategoryController(
        findCategory: $existingCategory,
        savedEntities: $savedEntities2,
        dispatchedEvents: $dispatchedEvents2,
    );

    $request2 = new Request(post: [
        'name' => 'Updated Category',
    ]);

    $controller2->update(1, $request2);

    expect($dispatchedEvents2)->toHaveCount(1)
        ->and($dispatchedEvents2[0])->toBeInstanceOf(CategoryUpdated::class)
        ->and($dispatchedEvents2[0]->getCategory()->getName())->toBe('Updated Category');

    // Test CategoryDeleted on destroy
    $existingCategory3 = createTestCategoryEntity(2, 'To Delete', 'to-delete');
    $dispatchedEvents3 = [];
    $deletedEntities3 = [];
    $controller3 = createCategoryController(
        findCategory: $existingCategory3,
        deletedEntities: $deletedEntities3,
        dispatchedEvents: $dispatchedEvents3,
    );

    $controller3->destroy(2);

    expect($dispatchedEvents3)->toHaveCount(1)
        ->and($dispatchedEvents3[0])->toBeInstanceOf(CategoryDeleted::class)
        ->and($dispatchedEvents3[0]->getCategory()->getName())->toBe('To Delete');
});

// Helper functions

function createTestCategoryEntity(
    int $id,
    string $name,
    string $slug,
    ?int $parentId = null,
): Category {
    $category = new Category();
    $category->id = $id;
    $category->name = $name;
    $category->slug = $slug;
    $category->parentId = $parentId;

    return $category;
}

function createMockCategoryAdminRepo(
    array $findAllResult = [],
    ?Category $findResult = null,
    array &$savedEntities = [],
    array &$deletedEntities = [],
): CategoryRepositoryInterface {
    return new class (
        $findAllResult,
        $findResult,
        $savedEntities,
        $deletedEntities,
    ) implements CategoryRepositoryInterface
    {
        public function __construct(
            private array $findAllResult,
            private ?Category $findResult,
            private array &$savedEntities,
            private array &$deletedEntities,
        ) {}

        public function find(
            int $id,
        ): ?Entity {
            return $this->findResult;
        }

        public function findOrFail(
            int $id,
        ): Entity {
            if ($this->findResult === null) {
                throw RepositoryException::entityNotFound(Category::class, $id);
            }

            return $this->findResult;
        }

        public function findAll(): array
        {
            return $this->findAllResult;
        }

        public function findBy(
            array $criteria,
        ): array {
            return [];
        }

        public function findOneBy(
            array $criteria,
        ): ?Entity {
            return null;
        }

        public function save(
            Entity $entity,
        ): void {
            if ($entity instanceof Category && $entity->id === null) {
                $entity->id = 99;
            }
            $this->savedEntities[] = $entity;
        }

        public function delete(
            Entity $entity,
        ): void {
            $this->deletedEntities[] = $entity;
        }

        public function findBySlug(
            string $slug,
        ): ?Category {
            return $this->findResult;
        }

        public function isSlugUnique(
            string $slug,
            ?int $excludeId = null,
        ): bool {
            return true;
        }

        public function findChildren(
            Category $parent,
        ): array {
            return [];
        }

        public function getPath(
            Category $category,
        ): array {
            return [$category];
        }

        public function findRoots(): array
        {
            return [];
        }

        public function getPostsForCategory(
            int $categoryId,
        ): array {
            return [];
        }

        public function getDescendantIds(
            int $categoryId,
        ): array {
            return [];
        }
    };
}

function createMockCategoryAdminPagination(
    array $items = [],
    int $totalItems = 0,
): PaginationServiceInterface {
    return new class ($items, $totalItems) implements PaginationServiceInterface
    {
        public function __construct(
            private array $items,
            private int $totalItems,
        ) {}

        public function paginate(
            array $items,
            int $totalItems,
            int $currentPage,
            ?int $perPage = null,
        ): PaginatedResult {
            return new PaginatedResult(
                items: $items,
                currentPage: $currentPage,
                totalItems: $totalItems,
                perPage: $perPage ?? 10,
                totalPages: $totalItems > 0 ? (int) ceil($totalItems / ($perPage ?? 10)) : 0,
                hasPreviousPage: $currentPage > 1,
                hasNextPage: $currentPage < (int) ceil($totalItems / ($perPage ?? 10)),
                pageNumbers: range(1, max(1, (int) ceil($totalItems / ($perPage ?? 10)))),
            );
        }

        public function calculateOffset(
            int $page,
            ?int $perPage = null,
        ): int {
            return ($page - 1) * ($perPage ?? 10);
        }

        public function getPerPage(): int
        {
            return 10;
        }
    };
}

function createMockCategoryAdminSlugGenerator(): SlugGeneratorInterface
{
    return new class () implements SlugGeneratorInterface
    {
        public function generate(
            string $title,
            ?Closure $uniquenessChecker = null,
        ): string {
            return strtolower(str_replace(' ', '-', $title));
        }
    };
}

function createMockCategoryAdminEventDispatcher(
    array &$dispatchedEvents = [],
): EventDispatcherInterface {
    return new class ($dispatchedEvents) implements EventDispatcherInterface
    {
        public function __construct(
            private array &$dispatchedEvents,
        ) {}

        public function dispatch(
            Event $event,
        ): void {
            $this->dispatchedEvents[] = $event;
        }
    };
}

function createMockCategoryAdminView(
    array &$capturedData = [],
): ViewInterface {
    return new class ($capturedData) implements ViewInterface
    {
        public function __construct(
            private array &$capturedData,
        ) {}

        public function render(
            string $template,
            array $data = [],
        ): Response {
            $this->capturedData = $data;

            return new Response("rendered: $template");
        }

        public function renderToString(
            string $template,
            array $data = [],
        ): string {
            $this->capturedData = $data;

            return "rendered: $template";
        }
    };
}

function createCategoryController(
    array $categories = [],
    int $totalCategories = 0,
    ?Category $findCategory = null,
    array &$capturedData = [],
    array &$savedEntities = [],
    array &$deletedEntities = [],
    array &$dispatchedEvents = [],
): CategoryAdminController {
    return new CategoryAdminController(
        categoryRepository: createMockCategoryAdminRepo(
            findAllResult: $categories,
            findResult: $findCategory,
            savedEntities: $savedEntities,
            deletedEntities: $deletedEntities,
        ),
        paginationService: createMockCategoryAdminPagination($categories, $totalCategories),
        slugGenerator: createMockCategoryAdminSlugGenerator(),
        eventDispatcher: createMockCategoryAdminEventDispatcher($dispatchedEvents),
        view: createMockCategoryAdminView($capturedData),
    );
}
