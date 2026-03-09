<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Unit\Admin\Api;

use Closure;
use Marko\AdminAuth\Attributes\RequiresPermission;
use Marko\AdminAuth\Middleware\AdminAuthMiddleware;
use Marko\Blog\Admin\Api\TagApiController;
use Marko\Blog\Entity\Tag;
use Marko\Blog\Repositories\TagRepositoryInterface;
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

it('creates TagApiController with CRUD actions returning JSON', function (): void {
    $reflection = new ReflectionClass(TagApiController::class);

    // Check class-level middleware
    $middlewareAttrs = $reflection->getAttributes(Middleware::class);
    expect($middlewareAttrs)->toHaveCount(1);
    $middleware = $middlewareAttrs[0]->newInstance();
    expect($middleware->middleware)->toContain(AdminAuthMiddleware::class);

    // Check index method
    $index = $reflection->getMethod('index');
    $indexRoute = $index->getAttributes(Get::class);
    expect($indexRoute)->toHaveCount(1)
        ->and($indexRoute[0]->newInstance()->path)->toBe('/admin/api/v1/blog/tags');
    $indexPerm = $index->getAttributes(RequiresPermission::class);
    expect($indexPerm)->toHaveCount(1)
        ->and($indexPerm[0]->newInstance()->permission)->toBe('blog.tags.view');

    // Check store method
    $store = $reflection->getMethod('store');
    $storeRoute = $store->getAttributes(PostRoute::class);
    expect($storeRoute)->toHaveCount(1)
        ->and($storeRoute[0]->newInstance()->path)->toBe('/admin/api/v1/blog/tags');
    $storePerm = $store->getAttributes(RequiresPermission::class);
    expect($storePerm)->toHaveCount(1)
        ->and($storePerm[0]->newInstance()->permission)->toBe('blog.tags.create');

    // Check show method
    $show = $reflection->getMethod('show');
    $showRoute = $show->getAttributes(Get::class);
    expect($showRoute)->toHaveCount(1)
        ->and($showRoute[0]->newInstance()->path)->toBe('/admin/api/v1/blog/tags/{id}');

    // Check update method
    $update = $reflection->getMethod('update');
    $updateRoute = $update->getAttributes(Put::class);
    expect($updateRoute)->toHaveCount(1)
        ->and($updateRoute[0]->newInstance()->path)->toBe('/admin/api/v1/blog/tags/{id}');

    // Check destroy method
    $destroy = $reflection->getMethod('destroy');
    $destroyRoute = $destroy->getAttributes(Delete::class);
    expect($destroyRoute)->toHaveCount(1)
        ->and($destroyRoute[0]->newInstance()->path)->toBe('/admin/api/v1/blog/tags/{id}');

    // Test list returns JSON
    $tags = [createApiTestTag(1, 'PHP', 'php')];
    $controller = createTagApiController(tags: $tags);
    $request = new Request(query: ['page' => '1']);
    $response = $controller->index($request);

    expect($response->statusCode())->toBe(200)
        ->and($response->headers()['Content-Type'])->toBe('application/json');

    $body = json_decode($response->body(), true);
    expect($body)->toHaveKey('data')
        ->and($body['data'])->toHaveCount(1)
        ->and($body['data'][0]['name'])->toBe('PHP')
        ->and($body['data'][0]['slug'])->toBe('php');

    // Test store returns 201 JSON
    $savedEntities = [];
    $controller2 = createTagApiController(savedEntities: $savedEntities);
    $storeRequest = new Request(post: [
        'name' => 'JavaScript',
    ]);
    $storeResponse = $controller2->store($storeRequest);

    expect($storeResponse->statusCode())->toBe(201);
    $storeBody = json_decode($storeResponse->body(), true);
    expect($storeBody['data']['name'])->toBe('JavaScript')
        ->and($savedEntities)->toHaveCount(1);

    // Test show returns single tag
    $tag = createApiTestTag(1, 'PHP', 'php');
    $controller3 = createTagApiController(findTag: $tag);
    $showResponse = $controller3->show(1);

    expect($showResponse->statusCode())->toBe(200);
    $showBody = json_decode($showResponse->body(), true);
    expect($showBody['data']['name'])->toBe('PHP');

    // Test destroy returns success
    $deletedEntities = [];
    $controller4 = createTagApiController(findTag: $tag, deletedEntities: $deletedEntities);
    $destroyResponse = $controller4->destroy(1);

    expect($destroyResponse->statusCode())->toBe(200);
    $destroyBody = json_decode($destroyResponse->body(), true);
    expect($destroyBody['data']['deleted'])->toBeTrue()
        ->and($deletedEntities)->toHaveCount(1);
});

// Helper functions

function createApiTestTag(
    int $id,
    string $name,
    string $slug,
): Tag {
    $tag = new Tag();
    $tag->id = $id;
    $tag->name = $name;
    $tag->slug = $slug;

    return $tag;
}

function createApiMockTagRepo(
    array $findAllResult = [],
    ?Tag $findResult = null,
    array &$savedEntities = [],
    array &$deletedEntities = [],
): TagRepositoryInterface {
    return new class (
        $findAllResult,
        $findResult,
        $savedEntities,
        $deletedEntities,
    ) implements TagRepositoryInterface
    {
        public function __construct(
            private array $findAllResult,
            private ?Tag $findResult,
            /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
            private array &$savedEntities,
            /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
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
                throw RepositoryException::entityNotFound(Tag::class, $id);
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

        public function existsBy(
            array $criteria,
        ): bool {
            return $this->findOneBy(criteria: $criteria) !== null;
        }

        public function save(
            Entity $entity,
        ): void {
            if ($entity instanceof Tag && $entity->id === null) {
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
        ): ?Tag {
            return null;
        }

        public function findByNameLike(
            string $name,
        ): array {
            return [];
        }

        public function isSlugUnique(
            string $slug,
            ?int $excludeId = null,
        ): bool {
            return true;
        }

        public function getPostsForTag(
            int $tagId,
        ): array {
            return [];
        }
    };
}

function createTagApiSlugGenerator(): SlugGeneratorInterface
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

function createTagApiController(
    array $tags = [],
    ?Tag $findTag = null,
    array &$savedEntities = [],
    array &$deletedEntities = [],
): TagApiController {
    return new TagApiController(
        tagRepository: createApiMockTagRepo(
            findAllResult: $tags,
            findResult: $findTag,
            savedEntities: $savedEntities,
            deletedEntities: $deletedEntities,
        ),
        slugGenerator: createTagApiSlugGenerator(),
    );
}
