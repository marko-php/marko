<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Unit\Admin\Api;

use Closure;
use Marko\AdminAuth\Attributes\RequiresPermission;
use Marko\AdminAuth\Middleware\AdminAuthMiddleware;
use Marko\Blog\Admin\Api\CategoryApiController;
use Marko\Blog\Entity\Category;
use Marko\Blog\Repositories\CategoryRepositoryInterface;
use Marko\Blog\Services\SlugGeneratorInterface;
use Marko\Database\Entity\Entity;
use Marko\Database\Exceptions\RepositoryException;
use Marko\Routing\Attributes\Delete;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Attributes\Post as PostRoute;
use Marko\Routing\Attributes\Put;
use Marko\Routing\Http\Request;
use ReflectionClass;

it('creates CategoryApiController with CRUD actions returning JSON', function (): void {
    $reflection = new ReflectionClass(CategoryApiController::class);

    // Check class-level middleware
    $middlewareAttrs = $reflection->getAttributes(Middleware::class);
    expect($middlewareAttrs)->toHaveCount(1);
    $middleware = $middlewareAttrs[0]->newInstance();
    expect($middleware->middleware)->toContain(AdminAuthMiddleware::class);

    // Check index method (list)
    $index = $reflection->getMethod('index');
    $indexRoute = $index->getAttributes(Get::class);
    expect($indexRoute)->toHaveCount(1);
    expect($indexRoute[0]->newInstance()->path)->toBe('/admin/api/v1/blog/categories');
    $indexPerm = $index->getAttributes(RequiresPermission::class);
    expect($indexPerm)->toHaveCount(1);
    expect($indexPerm[0]->newInstance()->permission)->toBe('blog.categories.view');

    // Check store method
    $store = $reflection->getMethod('store');
    $storeRoute = $store->getAttributes(PostRoute::class);
    expect($storeRoute)->toHaveCount(1);
    expect($storeRoute[0]->newInstance()->path)->toBe('/admin/api/v1/blog/categories');
    $storePerm = $store->getAttributes(RequiresPermission::class);
    expect($storePerm)->toHaveCount(1);
    expect($storePerm[0]->newInstance()->permission)->toBe('blog.categories.create');

    // Check show method
    $show = $reflection->getMethod('show');
    $showRoute = $show->getAttributes(Get::class);
    expect($showRoute)->toHaveCount(1);
    expect($showRoute[0]->newInstance()->path)->toBe('/admin/api/v1/blog/categories/{id}');

    // Check update method
    $update = $reflection->getMethod('update');
    $updateRoute = $update->getAttributes(Put::class);
    expect($updateRoute)->toHaveCount(1);
    expect($updateRoute[0]->newInstance()->path)->toBe('/admin/api/v1/blog/categories/{id}');

    // Check destroy method
    $destroy = $reflection->getMethod('destroy');
    $destroyRoute = $destroy->getAttributes(Delete::class);
    expect($destroyRoute)->toHaveCount(1);
    expect($destroyRoute[0]->newInstance()->path)->toBe('/admin/api/v1/blog/categories/{id}');

    // Test list returns JSON
    $categories = [createApiTestCategory(1, 'Tech', 'tech')];
    $controller = createCategoryApiController(categories: $categories);
    $request = new Request(query: ['page' => '1']);
    $response = $controller->index($request);

    expect($response->statusCode())->toBe(200)
        ->and($response->headers()['Content-Type'])->toBe('application/json');

    $body = json_decode($response->body(), true);
    expect($body)->toHaveKey('data')
        ->and($body['data'])->toHaveCount(1)
        ->and($body['data'][0]['name'])->toBe('Tech')
        ->and($body['data'][0]['slug'])->toBe('tech');

    // Test store returns 201 JSON
    $savedEntities = [];
    $controller2 = createCategoryApiController(savedEntities: $savedEntities);
    $storeRequest = new Request(post: [
        'name' => 'Science',
    ]);
    $storeResponse = $controller2->store($storeRequest);

    expect($storeResponse->statusCode())->toBe(201);
    $storeBody = json_decode($storeResponse->body(), true);
    expect($storeBody['data']['name'])->toBe('Science')
        ->and($savedEntities)->toHaveCount(1);

    // Test show returns single category
    $category = createApiTestCategory(1, 'Tech', 'tech');
    $controller3 = createCategoryApiController(findCategory: $category);
    $showResponse = $controller3->show(1);

    expect($showResponse->statusCode())->toBe(200);
    $showBody = json_decode($showResponse->body(), true);
    expect($showBody['data']['name'])->toBe('Tech');

    // Test destroy returns success
    $deletedEntities = [];
    $controller4 = createCategoryApiController(findCategory: $category, deletedEntities: $deletedEntities);
    $destroyResponse = $controller4->destroy(1);

    expect($destroyResponse->statusCode())->toBe(200);
    $destroyBody = json_decode($destroyResponse->body(), true);
    expect($destroyBody['data']['deleted'])->toBeTrue()
        ->and($deletedEntities)->toHaveCount(1);
});

// Helper functions

function createApiTestCategory(
    int $id,
    string $name,
    string $slug,
): Category {
    $category = new Category();
    $category->id = $id;
    $category->name = $name;
    $category->slug = $slug;

    return $category;
}

function createApiMockCategoryRepo(
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
            return null;
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

function createCategoryApiSlugGenerator(): SlugGeneratorInterface
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

function createCategoryApiController(
    array $categories = [],
    ?Category $findCategory = null,
    array &$savedEntities = [],
    array &$deletedEntities = [],
): CategoryApiController {
    return new CategoryApiController(
        categoryRepository: createApiMockCategoryRepo(
            findAllResult: $categories,
            findResult: $findCategory,
            savedEntities: $savedEntities,
            deletedEntities: $deletedEntities,
        ),
        slugGenerator: createCategoryApiSlugGenerator(),
    );
}
