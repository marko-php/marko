<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Repositories;

use Closure;
use Marko\Blog\Entity\Category;
use Marko\Blog\Events\Category\CategoryCreated;
use Marko\Blog\Events\Category\CategoryDeleted;
use Marko\Blog\Events\Category\CategoryUpdated;
use Marko\Blog\Repositories\CategoryRepository;
use Marko\Blog\Services\SlugGeneratorInterface;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Exceptions\RepositoryException;
use Marko\Database\Repository\Repository;
use Marko\Testing\Fake\FakeEventDispatcher;
use ReflectionClass;
use RuntimeException;

it('constructs with SlugGeneratorInterface plus parent params forwarded', function (): void {
    $connection = makeCategoryConnection();
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $slugGenerator = makeSlugGenerator();
    $dispatcher = new FakeEventDispatcher();

    $repo = new CategoryRepository(
        $connection,
        $metadataFactory,
        $hydrator,
        $slugGenerator,
        null,
        $dispatcher,
    );

    $reflection = new ReflectionClass($repo);

    // SlugGeneratorInterface is promoted on CategoryRepository
    $slugProp = $reflection->getProperty('slugGenerator');
    expect($slugProp->getDeclaringClass()->getName())->toBe(CategoryRepository::class);

    // EventDispatcherInterface is NOT a private property on CategoryRepository — it is inherited from Repository
    $dispatcherProp = $reflection->getProperty('eventDispatcher');
    expect($dispatcherProp->getDeclaringClass()->getName())->toBe(Repository::class);

    // The inherited eventDispatcher holds the passed dispatcher
    expect($dispatcherProp->getValue($repo))->toBe($dispatcher);
});

it('auto-generates slug on save', function (): void {
    $generated = new class ()
    {
        public ?string $slug = null;
    };

    $slugGenerator = new class ($generated) implements SlugGeneratorInterface
    {
        public function __construct(
            private object $capture,
        ) {}

        public function generate(
            string $title,
            ?Closure $uniquenessChecker = null,
        ): string {
            $this->capture->slug = strtolower(str_replace(' ', '-', $title));

            return $this->capture->slug;
        }
    };

    $repo = new CategoryRepository(
        makeCategoryConnection(),
        new EntityMetadataFactory(),
        new EntityHydrator(),
        $slugGenerator,
    );

    $category = new Category();
    $category->name = 'Auto Generated';

    $repo->save($category);

    expect($generated->slug)->toBe('auto-generated')
        ->and($category->slug)->toBe('auto-generated');
});

it('dispatches CategoryCreated event on new category', function (): void {
    $dispatcher = new FakeEventDispatcher();

    $repo = new CategoryRepository(
        makeCategoryConnection(lastInsertId: 1),
        new EntityMetadataFactory(),
        new EntityHydrator(),
        makeSlugGenerator(),
        null,
        $dispatcher,
    );

    $category = new Category();
    $category->name = 'New Category';
    $category->slug = 'new-category';

    $repo->save($category);

    $categoryCreatedEvents = array_filter(
        $dispatcher->dispatched,
        fn (object $e): bool => $e instanceof CategoryCreated,
    );

    expect($categoryCreatedEvents)->toHaveCount(1);
});

it('dispatches CategoryUpdated event on existing category', function (): void {
    $dispatcher = new FakeEventDispatcher();

    $repo = new CategoryRepository(
        makeCategoryConnection(),
        new EntityMetadataFactory(),
        new EntityHydrator(),
        makeSlugGenerator(),
        null,
        $dispatcher,
    );

    $category = new Category();
    $category->id = 5;
    $category->name = 'Existing Category';
    $category->slug = 'existing-category';

    $repo->save($category);

    $categoryUpdatedEvents = array_filter(
        $dispatcher->dispatched,
        fn (object $e): bool => $e instanceof CategoryUpdated,
    );

    expect($categoryUpdatedEvents)->toHaveCount(1);
});

it('dispatches CategoryDeleted event on delete', function (): void {
    $dispatcher = new FakeEventDispatcher();

    $repo = new CategoryRepository(
        makeDeletableCategoryConnection(),
        new EntityMetadataFactory(),
        new EntityHydrator(),
        makeSlugGenerator(),
        null,
        $dispatcher,
    );

    $category = new Category();
    $category->id = 1;
    $category->name = 'Delete Me';
    $category->slug = 'delete-me';

    $repo->delete($category);

    $deletedEvents = array_filter(
        $dispatcher->dispatched,
        fn (object $e): bool => $e instanceof CategoryDeleted,
    );

    expect($deletedEvents)->toHaveCount(1);
});

it('prevents deletion of category with posts', function (): void {
    $repo = new CategoryRepository(
        makeConnectionWithPostCount(3),
        new EntityMetadataFactory(),
        new EntityHydrator(),
        makeSlugGenerator(),
    );

    $category = new Category();
    $category->id = 1;
    $category->name = 'Has Posts';
    $category->slug = 'has-posts';

    expect(fn () => $repo->delete($category))
        ->toThrow(RepositoryException::class, 'Cannot delete category with associated posts');
});

it('prevents deletion of category with children', function (): void {
    $repo = new CategoryRepository(
        makeConnectionWithChildCount(2),
        new EntityMetadataFactory(),
        new EntityHydrator(),
        makeSlugGenerator(),
    );

    $category = new Category();
    $category->id = 1;
    $category->name = 'Has Children';
    $category->slug = 'has-children';

    expect(fn () => $repo->delete($category))
        ->toThrow(RepositoryException::class, 'Cannot delete category with child categories');
});

// Helpers

function makeCategoryConnection(
    array $queryResult = [],
    int $lastInsertId = 1,
): ConnectionInterface {
    return new class ($queryResult, $lastInsertId) implements ConnectionInterface
    {
        public function __construct(
            private array $queryResult,
            private int $lastInsertId,
        ) {}

        public function connect(): void {}

        public function disconnect(): void {}

        public function isConnected(): bool
        {
            return true;
        }

        public function query(
            string $sql,
            array $bindings = [],
        ): array {
            return $this->queryResult;
        }

        public function execute(
            string $sql,
            array $bindings = [],
        ): int {
            return 1;
        }

        public function prepare(
            string $sql,
        ): StatementInterface {
            throw new RuntimeException('Not implemented');
        }

        public function lastInsertId(): int
        {
            return $this->lastInsertId;
        }
    };
}

function makeDeletableCategoryConnection(): ConnectionInterface
{
    return new class () implements ConnectionInterface
    {
        public function connect(): void {}

        public function disconnect(): void {}

        public function isConnected(): bool
        {
            return true;
        }

        public function query(
            string $sql,
            array $bindings = [],
        ): array {
            if (str_contains($sql, 'post_categories') || str_contains($sql, 'parent_id')) {
                return [['count' => 0]];
            }

            return [];
        }

        public function execute(
            string $sql,
            array $bindings = [],
        ): int {
            return 1;
        }

        public function prepare(
            string $sql,
        ): StatementInterface {
            throw new RuntimeException('Not implemented');
        }

        public function lastInsertId(): int
        {
            return 1;
        }
    };
}

function makeConnectionWithPostCount(int $count): ConnectionInterface
{
    return new class ($count) implements ConnectionInterface
    {
        public function __construct(
            private int $count,
        ) {}

        public function connect(): void {}

        public function disconnect(): void {}

        public function isConnected(): bool
        {
            return true;
        }

        public function query(
            string $sql,
            array $bindings = [],
        ): array {
            if (str_contains($sql, 'post_categories')) {
                return [['count' => $this->count]];
            }

            return [];
        }

        public function execute(
            string $sql,
            array $bindings = [],
        ): int {
            return 1;
        }

        public function prepare(
            string $sql,
        ): StatementInterface {
            throw new RuntimeException('Not implemented');
        }

        public function lastInsertId(): int
        {
            return 1;
        }
    };
}

function makeConnectionWithChildCount(int $count): ConnectionInterface
{
    return new class ($count) implements ConnectionInterface
    {
        public function __construct(
            private int $count,
        ) {}

        public function connect(): void {}

        public function disconnect(): void {}

        public function isConnected(): bool
        {
            return true;
        }

        public function query(
            string $sql,
            array $bindings = [],
        ): array {
            if (str_contains($sql, 'post_categories')) {
                return [['count' => 0]];
            }
            if (str_contains($sql, 'parent_id')) {
                return [['count' => $this->count]];
            }

            return [];
        }

        public function execute(
            string $sql,
            array $bindings = [],
        ): int {
            return 1;
        }

        public function prepare(
            string $sql,
        ): StatementInterface {
            throw new RuntimeException('Not implemented');
        }

        public function lastInsertId(): int
        {
            return 1;
        }
    };
}

function makeSlugGenerator(): SlugGeneratorInterface
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
