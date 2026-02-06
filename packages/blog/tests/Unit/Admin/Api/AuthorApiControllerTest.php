<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Unit\Admin\Api;

use Closure;
use Marko\AdminAuth\Attributes\RequiresPermission;
use Marko\AdminAuth\Middleware\AdminAuthMiddleware;
use Marko\Blog\Admin\Api\AuthorApiController;
use Marko\Blog\Entity\Author;
use Marko\Blog\Repositories\AuthorRepositoryInterface;
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

it('creates AuthorApiController with CRUD actions returning JSON', function (): void {
    $reflection = new ReflectionClass(AuthorApiController::class);

    // Check class-level middleware
    $middlewareAttrs = $reflection->getAttributes(Middleware::class);
    expect($middlewareAttrs)->toHaveCount(1);
    $middleware = $middlewareAttrs[0]->newInstance();
    expect($middleware->middleware)->toContain(AdminAuthMiddleware::class);

    // Check index method (list)
    $index = $reflection->getMethod('index');
    $indexRoute = $index->getAttributes(Get::class);
    expect($indexRoute)->toHaveCount(1);
    expect($indexRoute[0]->newInstance()->path)->toBe('/admin/api/v1/blog/authors');
    $indexPerm = $index->getAttributes(RequiresPermission::class);
    expect($indexPerm)->toHaveCount(1);
    expect($indexPerm[0]->newInstance()->permission)->toBe('blog.authors.view');

    // Check show method
    $show = $reflection->getMethod('show');
    $showRoute = $show->getAttributes(Get::class);
    expect($showRoute)->toHaveCount(1);
    expect($showRoute[0]->newInstance()->path)->toBe('/admin/api/v1/blog/authors/{id}');

    // Check store method (create)
    $store = $reflection->getMethod('store');
    $storeRoute = $store->getAttributes(PostRoute::class);
    expect($storeRoute)->toHaveCount(1);
    expect($storeRoute[0]->newInstance()->path)->toBe('/admin/api/v1/blog/authors');
    $storePerm = $store->getAttributes(RequiresPermission::class);
    expect($storePerm)->toHaveCount(1);
    expect($storePerm[0]->newInstance()->permission)->toBe('blog.authors.create');

    // Check update method
    $update = $reflection->getMethod('update');
    $updateRoute = $update->getAttributes(Put::class);
    expect($updateRoute)->toHaveCount(1);
    expect($updateRoute[0]->newInstance()->path)->toBe('/admin/api/v1/blog/authors/{id}');

    // Check destroy method (delete)
    $destroy = $reflection->getMethod('destroy');
    $destroyRoute = $destroy->getAttributes(Delete::class);
    expect($destroyRoute)->toHaveCount(1);
    expect($destroyRoute[0]->newInstance()->path)->toBe('/admin/api/v1/blog/authors/{id}');

    // Test list returns JSON
    $authors = [createApiTestAuthor(1, 'John Doe', 'john-doe')];
    $controller = createAuthorApiController(authors: $authors);
    $request = new Request(query: ['page' => '1']);
    $response = $controller->index($request);

    expect($response->statusCode())->toBe(200)
        ->and($response->headers()['Content-Type'])->toBe('application/json');

    $body = json_decode($response->body(), true);
    expect($body)->toHaveKey('data')
        ->and($body['data'])->toHaveCount(1)
        ->and($body['data'][0]['name'])->toBe('John Doe')
        ->and($body['data'][0]['slug'])->toBe('john-doe');

    // Test store returns 201 JSON
    $savedEntities = [];
    $controller2 = createAuthorApiController(savedEntities: $savedEntities);
    $storeRequest = new Request(post: [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'bio' => 'A bio.',
    ]);
    $storeResponse = $controller2->store($storeRequest);

    expect($storeResponse->statusCode())->toBe(201);
    $storeBody = json_decode($storeResponse->body(), true);
    expect($storeBody['data']['name'])->toBe('Jane Doe')
        ->and($savedEntities)->toHaveCount(1);

    // Test show returns single author
    $author = createApiTestAuthor(1, 'John Doe', 'john-doe');
    $controller3 = createAuthorApiController(findAuthor: $author);
    $showResponse = $controller3->show(1);

    expect($showResponse->statusCode())->toBe(200);
    $showBody = json_decode($showResponse->body(), true);
    expect($showBody['data']['name'])->toBe('John Doe');

    // Test destroy returns success
    $deletedEntities = [];
    $controller4 = createAuthorApiController(findAuthor: $author, deletedEntities: $deletedEntities);
    $destroyResponse = $controller4->destroy(1);

    expect($destroyResponse->statusCode())->toBe(200);
    $destroyBody = json_decode($destroyResponse->body(), true);
    expect($destroyBody['data']['deleted'])->toBeTrue()
        ->and($deletedEntities)->toHaveCount(1);
});

// Helper functions

function createApiTestAuthor(
    int $id,
    string $name,
    string $slug,
): Author {
    $author = new Author();
    $author->id = $id;
    $author->name = $name;
    $author->email = 'test@example.com';
    $author->slug = $slug;

    return $author;
}

function createApiMockAuthorRepo(
    array $findAllResult = [],
    ?Author $findResult = null,
    array &$savedEntities = [],
    array &$deletedEntities = [],
): AuthorRepositoryInterface {
    return new class (
        $findAllResult,
        $findResult,
        $savedEntities,
        $deletedEntities,
    ) implements AuthorRepositoryInterface
    {
        public function __construct(
            private array $findAllResult,
            private ?Author $findResult,
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
                throw RepositoryException::entityNotFound(Author::class, $id);
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
            if ($entity instanceof Author && $entity->id === null) {
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
        ): ?Author {
            return $this->findResult;
        }

        public function findByEmail(
            string $email,
        ): ?Author {
            return $this->findResult;
        }

        public function isSlugUnique(
            string $slug,
            ?int $excludeId = null,
        ): bool {
            return true;
        }
    };
}

function createAuthorApiSlugGenerator(): SlugGeneratorInterface
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

function createAuthorApiController(
    array $authors = [],
    ?Author $findAuthor = null,
    array &$savedEntities = [],
    array &$deletedEntities = [],
): AuthorApiController {
    return new AuthorApiController(
        authorRepository: createApiMockAuthorRepo(
            findAllResult: $authors,
            findResult: $findAuthor,
            savedEntities: $savedEntities,
            deletedEntities: $deletedEntities,
        ),
        slugGenerator: createAuthorApiSlugGenerator(),
    );
}
