<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Unit\Admin\Controllers;

use Closure;
use Marko\AdminAuth\Attributes\RequiresPermission;
use Marko\AdminAuth\Middleware\AdminAuthMiddleware;
use Marko\Blog\Admin\Controllers\AuthorAdminController;
use Marko\Blog\Dto\PaginatedResult;
use Marko\Blog\Entity\Author;
use Marko\Blog\Events\Author\AuthorCreated;
use Marko\Blog\Events\Author\AuthorDeleted;
use Marko\Blog\Events\Author\AuthorUpdated;
use Marko\Blog\Repositories\AuthorRepositoryInterface;
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

it('creates AuthorAdminController with list, create, store, edit, update, delete actions', function (): void {
    $reflection = new ReflectionClass(AuthorAdminController::class);

    expect($reflection->hasMethod('index'))->toBeTrue()
        ->and($reflection->hasMethod('create'))->toBeTrue()
        ->and($reflection->hasMethod('store'))->toBeTrue()
        ->and($reflection->hasMethod('edit'))->toBeTrue()
        ->and($reflection->hasMethod('update'))->toBeTrue()
        ->and($reflection->hasMethod('destroy'))->toBeTrue();
});

it('requires blog.authors.view permission for author list', function (): void {
    $reflection = new ReflectionClass(AuthorAdminController::class);
    $method = $reflection->getMethod('index');

    // Check route attribute
    $routeAttrs = $method->getAttributes(Get::class);
    expect($routeAttrs)->toHaveCount(1);
    $route = $routeAttrs[0]->newInstance();
    expect($route->path)->toBe('/admin/blog/authors');

    // Check permission attribute
    $permAttrs = $method->getAttributes(RequiresPermission::class);
    expect($permAttrs)->toHaveCount(1);
    $perm = $permAttrs[0]->newInstance();
    expect($perm->permission)->toBe('blog.authors.view');

    // Test actual behavior - returns paginated authors
    $authors = [
        createTestAuthorEntity(1, 'John Doe', 'john-doe'),
        createTestAuthorEntity(2, 'Jane Smith', 'jane-smith'),
    ];
    $capturedData = [];
    $controller = createAuthorController(
        authors: $authors,
        totalAuthors: 2,
        capturedData: $capturedData,
    );

    $request = new Request(query: ['page' => '1']);
    $response = $controller->index($request);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->statusCode())->toBe(200)
        ->and($response->body())->toContain('blog::admin/author/index')
        ->and($capturedData)->toHaveKey('authors')
        ->and($capturedData['authors'])->toBeInstanceOf(PaginatedResult::class)
        ->and($capturedData['authors']->items)->toHaveCount(2);
});

it('renders create form on GET /admin/blog/authors/create with blog.authors.create permission', function (): void {
    $reflection = new ReflectionClass(AuthorAdminController::class);
    $method = $reflection->getMethod('create');

    $routeAttrs = $method->getAttributes(Get::class);
    expect($routeAttrs)->toHaveCount(1);
    $route = $routeAttrs[0]->newInstance();
    expect($route->path)->toBe('/admin/blog/authors/create');

    $permAttrs = $method->getAttributes(RequiresPermission::class);
    expect($permAttrs)->toHaveCount(1);
    $perm = $permAttrs[0]->newInstance();
    expect($perm->permission)->toBe('blog.authors.create');

    $capturedData = [];
    $controller = createAuthorController(capturedData: $capturedData);

    $response = $controller->create();

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->statusCode())->toBe(200)
        ->and($response->body())->toContain('blog::admin/author/create');
});

it('creates new author on POST /admin/blog/authors with valid data', function (): void {
    $reflection = new ReflectionClass(AuthorAdminController::class);
    $method = $reflection->getMethod('store');

    $routeAttrs = $method->getAttributes(PostRoute::class);
    expect($routeAttrs)->toHaveCount(1);
    $route = $routeAttrs[0]->newInstance();
    expect($route->path)->toBe('/admin/blog/authors');

    $permAttrs = $method->getAttributes(RequiresPermission::class);
    expect($permAttrs)->toHaveCount(1);
    $perm = $permAttrs[0]->newInstance();
    expect($perm->permission)->toBe('blog.authors.create');

    $savedEntities = [];
    $capturedData = [];
    $controller = createAuthorController(
        capturedData: $capturedData,
        savedEntities: $savedEntities,
    );

    $request = new Request(post: [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'bio' => 'A writer.',
    ]);

    $response = $controller->store($request);

    expect($response->statusCode())->toBe(302)
        ->and($response->headers())->toHaveKey('Location')
        ->and($savedEntities)->toHaveCount(1)
        ->and($savedEntities[0])->toBeInstanceOf(Author::class)
        ->and($savedEntities[0]->name)->toBe('John Doe')
        ->and($savedEntities[0]->email)->toBe('john@example.com')
        ->and($savedEntities[0]->bio)->toBe('A writer.');
});

it('returns validation errors on POST /admin/blog/authors with invalid data', function (): void {
    $savedEntities = [];
    $capturedData = [];
    $controller = createAuthorController(
        capturedData: $capturedData,
        savedEntities: $savedEntities,
    );

    $request = new Request(post: [
        'name' => '',
        'email' => '',
    ]);

    $response = $controller->store($request);

    expect($response->statusCode())->toBe(200)
        ->and($response->body())->toContain('blog::admin/author/create')
        ->and($capturedData)->toHaveKey('errors')
        ->and($capturedData['errors'])->toContain('Name is required')
        ->and($capturedData['errors'])->toContain('Email is required')
        ->and($savedEntities)->toHaveCount(0);
});

it('renders edit form on GET /admin/blog/authors/{id}/edit with blog.authors.edit permission', function (): void {
    $reflection = new ReflectionClass(AuthorAdminController::class);
    $method = $reflection->getMethod('edit');

    $routeAttrs = $method->getAttributes(Get::class);
    expect($routeAttrs)->toHaveCount(1);
    $route = $routeAttrs[0]->newInstance();
    expect($route->path)->toBe('/admin/blog/authors/{id}/edit');

    $permAttrs = $method->getAttributes(RequiresPermission::class);
    expect($permAttrs)->toHaveCount(1);
    $perm = $permAttrs[0]->newInstance();
    expect($perm->permission)->toBe('blog.authors.edit');

    $author = createTestAuthorEntity(1, 'John Doe', 'john-doe');
    $capturedData = [];
    $controller = createAuthorController(
        findAuthor: $author,
        capturedData: $capturedData,
    );

    $response = $controller->edit(1);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->statusCode())->toBe(200)
        ->and($response->body())->toContain('blog::admin/author/edit')
        ->and($capturedData)->toHaveKey('author')
        ->and($capturedData['author']->getName())->toBe('John Doe');
});

it('returns 404 when editing non-existent author', function (): void {
    $controller = createAuthorController();

    $response = $controller->edit(999);

    expect($response->statusCode())->toBe(404)
        ->and($response->body())->toContain('not found');
});

it('updates author on PUT /admin/blog/authors/{id} with valid data', function (): void {
    $reflection = new ReflectionClass(AuthorAdminController::class);
    $method = $reflection->getMethod('update');

    $routeAttrs = $method->getAttributes(Put::class);
    expect($routeAttrs)->toHaveCount(1);
    $route = $routeAttrs[0]->newInstance();
    expect($route->path)->toBe('/admin/blog/authors/{id}');

    $permAttrs = $method->getAttributes(RequiresPermission::class);
    expect($permAttrs)->toHaveCount(1);
    $perm = $permAttrs[0]->newInstance();
    expect($perm->permission)->toBe('blog.authors.edit');

    $existingAuthor = createTestAuthorEntity(1, 'Old Name', 'old-name');
    $savedEntities = [];
    $controller = createAuthorController(
        findAuthor: $existingAuthor,
        savedEntities: $savedEntities,
    );

    $request = new Request(post: [
        'name' => 'Updated Name',
        'email' => 'updated@example.com',
        'bio' => 'Updated bio.',
    ]);

    $response = $controller->update(1, $request);

    expect($response->statusCode())->toBe(302)
        ->and($response->headers())->toHaveKey('Location')
        ->and($savedEntities)->toHaveCount(1)
        ->and($savedEntities[0]->name)->toBe('Updated Name')
        ->and($savedEntities[0]->email)->toBe('updated@example.com')
        ->and($savedEntities[0]->bio)->toBe('Updated bio.');
});

it('deletes author on DELETE /admin/blog/authors/{id} with blog.authors.delete permission', function (): void {
    $reflection = new ReflectionClass(AuthorAdminController::class);
    $method = $reflection->getMethod('destroy');

    $routeAttrs = $method->getAttributes(Delete::class);
    expect($routeAttrs)->toHaveCount(1);
    $route = $routeAttrs[0]->newInstance();
    expect($route->path)->toBe('/admin/blog/authors/{id}');

    $permAttrs = $method->getAttributes(RequiresPermission::class);
    expect($permAttrs)->toHaveCount(1);
    $perm = $permAttrs[0]->newInstance();
    expect($perm->permission)->toBe('blog.authors.delete');

    $existingAuthor = createTestAuthorEntity(1, 'Author to Delete', 'author-to-delete');
    $deletedEntities = [];
    $controller = createAuthorController(
        findAuthor: $existingAuthor,
        deletedEntities: $deletedEntities,
    );

    $response = $controller->destroy(1);

    expect($response->statusCode())->toBe(302)
        ->and($response->headers())->toHaveKey('Location')
        ->and($deletedEntities)->toHaveCount(1)
        ->and($deletedEntities[0]->id)->toBe(1);
});

it('applies AdminAuthMiddleware to AuthorAdminController', function (): void {
    $reflection = new ReflectionClass(AuthorAdminController::class);

    $middlewareAttrs = $reflection->getAttributes(Middleware::class);
    expect($middlewareAttrs)->toHaveCount(1);

    $middleware = $middlewareAttrs[0]->newInstance();
    expect($middleware->middleware)->toContain(AdminAuthMiddleware::class);
});

it('dispatches AuthorCreated, AuthorUpdated, and AuthorDeleted events', function (): void {
    // Test AuthorCreated on store
    $dispatchedEvents = [];
    $savedEntities = [];
    $controller = createAuthorController(
        savedEntities: $savedEntities,
        dispatchedEvents: $dispatchedEvents,
    );

    $request = new Request(post: [
        'name' => 'New Author',
        'email' => 'new@example.com',
    ]);

    $controller->store($request);

    expect($dispatchedEvents)->toHaveCount(1)
        ->and($dispatchedEvents[0])->toBeInstanceOf(AuthorCreated::class)
        ->and($dispatchedEvents[0]->getAuthor()->getName())->toBe('New Author');

    // Test AuthorUpdated on update
    $existingAuthor = createTestAuthorEntity(1, 'Existing', 'existing');
    $dispatchedEvents2 = [];
    $savedEntities2 = [];
    $controller2 = createAuthorController(
        findAuthor: $existingAuthor,
        savedEntities: $savedEntities2,
        dispatchedEvents: $dispatchedEvents2,
    );

    $request2 = new Request(post: [
        'name' => 'Updated Author',
        'email' => 'updated@example.com',
    ]);

    $controller2->update(1, $request2);

    expect($dispatchedEvents2)->toHaveCount(1)
        ->and($dispatchedEvents2[0])->toBeInstanceOf(AuthorUpdated::class)
        ->and($dispatchedEvents2[0]->getAuthor()->getName())->toBe('Updated Author');

    // Test AuthorDeleted on destroy
    $existingAuthor3 = createTestAuthorEntity(2, 'To Delete', 'to-delete');
    $dispatchedEvents3 = [];
    $deletedEntities3 = [];
    $controller3 = createAuthorController(
        findAuthor: $existingAuthor3,
        deletedEntities: $deletedEntities3,
        dispatchedEvents: $dispatchedEvents3,
    );

    $controller3->destroy(2);

    expect($dispatchedEvents3)->toHaveCount(1)
        ->and($dispatchedEvents3[0])->toBeInstanceOf(AuthorDeleted::class)
        ->and($dispatchedEvents3[0]->getAuthor()->getName())->toBe('To Delete');
});

// Helper functions

function createTestAuthorEntity(
    int $id,
    string $name,
    string $slug,
    ?string $bio = null,
): Author {
    $author = new Author();
    $author->id = $id;
    $author->name = $name;
    $author->email = 'test@example.com';
    $author->slug = $slug;
    $author->bio = $bio;

    return $author;
}

function createMockAuthorAdminRepo(
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

function createMockAuthorAdminPagination(
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

function createMockAuthorAdminSlugGenerator(): SlugGeneratorInterface
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

function createMockAuthorAdminEventDispatcher(
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

function createMockAuthorAdminView(
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

function createAuthorController(
    array $authors = [],
    int $totalAuthors = 0,
    ?Author $findAuthor = null,
    array &$capturedData = [],
    array &$savedEntities = [],
    array &$deletedEntities = [],
    array &$dispatchedEvents = [],
): AuthorAdminController {
    return new AuthorAdminController(
        authorRepository: createMockAuthorAdminRepo(
            findAllResult: $authors,
            findResult: $findAuthor,
            savedEntities: $savedEntities,
            deletedEntities: $deletedEntities,
        ),
        paginationService: createMockAuthorAdminPagination($authors, $totalAuthors),
        slugGenerator: createMockAuthorAdminSlugGenerator(),
        eventDispatcher: createMockAuthorAdminEventDispatcher($dispatchedEvents),
        view: createMockAuthorAdminView($capturedData),
    );
}
