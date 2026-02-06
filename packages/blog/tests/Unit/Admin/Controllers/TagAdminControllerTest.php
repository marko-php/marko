<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Unit\Admin\Controllers;

use Closure;
use Marko\AdminAuth\Attributes\RequiresPermission;
use Marko\AdminAuth\Middleware\AdminAuthMiddleware;
use Marko\Blog\Admin\Controllers\TagAdminController;
use Marko\Blog\Dto\PaginatedResult;
use Marko\Blog\Entity\Tag;
use Marko\Blog\Events\Tag\TagCreated;
use Marko\Blog\Events\Tag\TagDeleted;
use Marko\Blog\Events\Tag\TagUpdated;
use Marko\Blog\Repositories\TagRepositoryInterface;
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

it('creates TagAdminController with list, create, store, edit, update, delete actions', function (): void {
    $reflection = new ReflectionClass(TagAdminController::class);

    expect($reflection->hasMethod('index'))->toBeTrue()
        ->and($reflection->hasMethod('create'))->toBeTrue()
        ->and($reflection->hasMethod('store'))->toBeTrue()
        ->and($reflection->hasMethod('edit'))->toBeTrue()
        ->and($reflection->hasMethod('update'))->toBeTrue()
        ->and($reflection->hasMethod('destroy'))->toBeTrue();
});

it('requires blog.tags.view permission for tag list', function (): void {
    $reflection = new ReflectionClass(TagAdminController::class);
    $method = $reflection->getMethod('index');

    // Check route attribute
    $routeAttrs = $method->getAttributes(Get::class);
    expect($routeAttrs)->toHaveCount(1);
    $route = $routeAttrs[0]->newInstance();
    expect($route->path)->toBe('/admin/blog/tags');

    // Check permission attribute
    $permAttrs = $method->getAttributes(RequiresPermission::class);
    expect($permAttrs)->toHaveCount(1);
    $perm = $permAttrs[0]->newInstance();
    expect($perm->permission)->toBe('blog.tags.view');

    // Test actual behavior - returns paginated tags
    $tags = [
        createTestTagEntity(1, 'PHP', 'php'),
        createTestTagEntity(2, 'Laravel', 'laravel'),
    ];
    $capturedData = [];
    $controller = createTagController(
        tags: $tags,
        totalTags: 2,
        capturedData: $capturedData,
    );

    $request = new Request(query: ['page' => '1']);
    $response = $controller->index($request);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->statusCode())->toBe(200)
        ->and($response->body())->toContain('blog::admin/tag/index')
        ->and($capturedData)->toHaveKey('tags')
        ->and($capturedData['tags'])->toBeInstanceOf(PaginatedResult::class)
        ->and($capturedData['tags']->items)->toHaveCount(2);
});

it('renders create form on GET /admin/blog/tags/create with blog.tags.create permission', function (): void {
    $reflection = new ReflectionClass(TagAdminController::class);
    $method = $reflection->getMethod('create');

    $routeAttrs = $method->getAttributes(Get::class);
    expect($routeAttrs)->toHaveCount(1);
    $route = $routeAttrs[0]->newInstance();
    expect($route->path)->toBe('/admin/blog/tags/create');

    $permAttrs = $method->getAttributes(RequiresPermission::class);
    expect($permAttrs)->toHaveCount(1);
    $perm = $permAttrs[0]->newInstance();
    expect($perm->permission)->toBe('blog.tags.create');

    $capturedData = [];
    $controller = createTagController(capturedData: $capturedData);

    $response = $controller->create();

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->statusCode())->toBe(200)
        ->and($response->body())->toContain('blog::admin/tag/create');
});

it('creates new tag on POST /admin/blog/tags with valid data', function (): void {
    $reflection = new ReflectionClass(TagAdminController::class);
    $method = $reflection->getMethod('store');

    $routeAttrs = $method->getAttributes(PostRoute::class);
    expect($routeAttrs)->toHaveCount(1);
    $route = $routeAttrs[0]->newInstance();
    expect($route->path)->toBe('/admin/blog/tags');

    $permAttrs = $method->getAttributes(RequiresPermission::class);
    expect($permAttrs)->toHaveCount(1);
    $perm = $permAttrs[0]->newInstance();
    expect($perm->permission)->toBe('blog.tags.create');

    $savedEntities = [];
    $capturedData = [];
    $controller = createTagController(
        capturedData: $capturedData,
        savedEntities: $savedEntities,
    );

    $request = new Request(post: [
        'name' => 'New Tag',
    ]);

    $response = $controller->store($request);

    expect($response->statusCode())->toBe(302)
        ->and($response->headers())->toHaveKey('Location')
        ->and($savedEntities)->toHaveCount(1)
        ->and($savedEntities[0])->toBeInstanceOf(Tag::class)
        ->and($savedEntities[0]->name)->toBe('New Tag');
});

it('returns validation errors on POST /admin/blog/tags with invalid data', function (): void {
    $savedEntities = [];
    $capturedData = [];
    $controller = createTagController(
        capturedData: $capturedData,
        savedEntities: $savedEntities,
    );

    $request = new Request(post: [
        'name' => '',
    ]);

    $response = $controller->store($request);

    expect($response->statusCode())->toBe(200)
        ->and($response->body())->toContain('blog::admin/tag/create')
        ->and($capturedData)->toHaveKey('errors')
        ->and($capturedData['errors'])->toContain('Name is required')
        ->and($savedEntities)->toHaveCount(0);
});

it('renders edit form on GET /admin/blog/tags/{id}/edit with blog.tags.edit permission', function (): void {
    $reflection = new ReflectionClass(TagAdminController::class);
    $method = $reflection->getMethod('edit');

    $routeAttrs = $method->getAttributes(Get::class);
    expect($routeAttrs)->toHaveCount(1);
    $route = $routeAttrs[0]->newInstance();
    expect($route->path)->toBe('/admin/blog/tags/{id}/edit');

    $permAttrs = $method->getAttributes(RequiresPermission::class);
    expect($permAttrs)->toHaveCount(1);
    $perm = $permAttrs[0]->newInstance();
    expect($perm->permission)->toBe('blog.tags.edit');

    $tag = createTestTagEntity(1, 'PHP', 'php');
    $capturedData = [];
    $controller = createTagController(
        findTag: $tag,
        capturedData: $capturedData,
    );

    $response = $controller->edit(1);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->statusCode())->toBe(200)
        ->and($response->body())->toContain('blog::admin/tag/edit')
        ->and($capturedData)->toHaveKey('tag')
        ->and($capturedData['tag']->getName())->toBe('PHP');
});

it('returns 404 when editing non-existent tag', function (): void {
    $controller = createTagController();

    $response = $controller->edit(999);

    expect($response->statusCode())->toBe(404)
        ->and($response->body())->toContain('not found');
});

it('updates tag on PUT /admin/blog/tags/{id} with valid data', function (): void {
    $reflection = new ReflectionClass(TagAdminController::class);
    $method = $reflection->getMethod('update');

    $routeAttrs = $method->getAttributes(Put::class);
    expect($routeAttrs)->toHaveCount(1);
    $route = $routeAttrs[0]->newInstance();
    expect($route->path)->toBe('/admin/blog/tags/{id}');

    $permAttrs = $method->getAttributes(RequiresPermission::class);
    expect($permAttrs)->toHaveCount(1);
    $perm = $permAttrs[0]->newInstance();
    expect($perm->permission)->toBe('blog.tags.edit');

    $existingTag = createTestTagEntity(1, 'Old Name', 'old-name');
    $savedEntities = [];
    $controller = createTagController(
        findTag: $existingTag,
        savedEntities: $savedEntities,
    );

    $request = new Request(post: [
        'name' => 'Updated Name',
    ]);

    $response = $controller->update(1, $request);

    expect($response->statusCode())->toBe(302)
        ->and($response->headers())->toHaveKey('Location')
        ->and($savedEntities)->toHaveCount(1)
        ->and($savedEntities[0]->name)->toBe('Updated Name');
});

it('deletes tag on DELETE /admin/blog/tags/{id} with blog.tags.delete permission', function (): void {
    $reflection = new ReflectionClass(TagAdminController::class);
    $method = $reflection->getMethod('destroy');

    $routeAttrs = $method->getAttributes(Delete::class);
    expect($routeAttrs)->toHaveCount(1);
    $route = $routeAttrs[0]->newInstance();
    expect($route->path)->toBe('/admin/blog/tags/{id}');

    $permAttrs = $method->getAttributes(RequiresPermission::class);
    expect($permAttrs)->toHaveCount(1);
    $perm = $permAttrs[0]->newInstance();
    expect($perm->permission)->toBe('blog.tags.delete');

    $existingTag = createTestTagEntity(1, 'Tag to Delete', 'tag-to-delete');
    $deletedEntities = [];
    $controller = createTagController(
        findTag: $existingTag,
        deletedEntities: $deletedEntities,
    );

    $response = $controller->destroy(1);

    expect($response->statusCode())->toBe(302)
        ->and($response->headers())->toHaveKey('Location')
        ->and($deletedEntities)->toHaveCount(1)
        ->and($deletedEntities[0]->id)->toBe(1);
});

it('applies AdminAuthMiddleware to TagAdminController', function (): void {
    $reflection = new ReflectionClass(TagAdminController::class);

    $middlewareAttrs = $reflection->getAttributes(Middleware::class);
    expect($middlewareAttrs)->toHaveCount(1);

    $middleware = $middlewareAttrs[0]->newInstance();
    expect($middleware->middleware)->toContain(AdminAuthMiddleware::class);
});

it('dispatches TagCreated, TagUpdated, and TagDeleted events', function (): void {
    // Test TagCreated on store
    $dispatchedEvents = [];
    $savedEntities = [];
    $controller = createTagController(
        savedEntities: $savedEntities,
        dispatchedEvents: $dispatchedEvents,
    );

    $request = new Request(post: [
        'name' => 'New Tag',
    ]);

    $controller->store($request);

    expect($dispatchedEvents)->toHaveCount(1)
        ->and($dispatchedEvents[0])->toBeInstanceOf(TagCreated::class)
        ->and($dispatchedEvents[0]->getTag()->getName())->toBe('New Tag');

    // Test TagUpdated on update
    $existingTag = createTestTagEntity(1, 'Existing', 'existing');
    $dispatchedEvents2 = [];
    $savedEntities2 = [];
    $controller2 = createTagController(
        findTag: $existingTag,
        savedEntities: $savedEntities2,
        dispatchedEvents: $dispatchedEvents2,
    );

    $request2 = new Request(post: [
        'name' => 'Updated Tag',
    ]);

    $controller2->update(1, $request2);

    expect($dispatchedEvents2)->toHaveCount(1)
        ->and($dispatchedEvents2[0])->toBeInstanceOf(TagUpdated::class)
        ->and($dispatchedEvents2[0]->getTag()->getName())->toBe('Updated Tag');

    // Test TagDeleted on destroy
    $existingTag3 = createTestTagEntity(2, 'To Delete', 'to-delete');
    $dispatchedEvents3 = [];
    $deletedEntities3 = [];
    $controller3 = createTagController(
        findTag: $existingTag3,
        deletedEntities: $deletedEntities3,
        dispatchedEvents: $dispatchedEvents3,
    );

    $controller3->destroy(2);

    expect($dispatchedEvents3)->toHaveCount(1)
        ->and($dispatchedEvents3[0])->toBeInstanceOf(TagDeleted::class)
        ->and($dispatchedEvents3[0]->getTag()->getName())->toBe('To Delete');
});

// Helper functions

function createTestTagEntity(
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

function createMockTagAdminRepo(
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
            return $this->findResult;
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

function createMockTagAdminPagination(
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

function createMockTagAdminSlugGenerator(): SlugGeneratorInterface
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

function createMockTagAdminEventDispatcher(
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

function createMockTagAdminView(
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

function createTagController(
    array $tags = [],
    int $totalTags = 0,
    ?Tag $findTag = null,
    array &$capturedData = [],
    array &$savedEntities = [],
    array &$deletedEntities = [],
    array &$dispatchedEvents = [],
): TagAdminController {
    return new TagAdminController(
        tagRepository: createMockTagAdminRepo(
            findAllResult: $tags,
            findResult: $findTag,
            savedEntities: $savedEntities,
            deletedEntities: $deletedEntities,
        ),
        paginationService: createMockTagAdminPagination($tags, $totalTags),
        slugGenerator: createMockTagAdminSlugGenerator(),
        eventDispatcher: createMockTagAdminEventDispatcher($dispatchedEvents),
        view: createMockTagAdminView($capturedData),
    );
}
