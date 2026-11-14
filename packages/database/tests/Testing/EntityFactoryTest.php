<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Testing;

use Marko\Core\Container\Container;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Events\EntityCreated;
use Marko\Database\Events\EntityCreating;
use Marko\Database\Exceptions\EntityFactoryException;
use Marko\Database\Repository\Repository;
use Marko\Database\Testing\EntityFactory;
use Marko\Testing\Fake\FakeEventDispatcher;
use RuntimeException;

#[Table('factory_posts')]
class FactoryPost extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column]
    public string $title = '';

    #[Column]
    public string $status = 'draft';
}

/**
 * @extends Repository<FactoryPost>
 */
class FactoryPostRepository extends Repository
{
    protected const string ENTITY_CLASS = FactoryPost::class;
}

/**
 * @extends EntityFactory<FactoryPost>
 */
class FactoryPostFactory extends EntityFactory
{
    protected const string REPOSITORY = FactoryPostRepository::class;

    private int $number = 0;

    protected function definition(): FactoryPost
    {
        $post = new FactoryPost();
        $post->title = 'Post ' . ++$this->number;

        return $post;
    }
}

/**
 * @extends EntityFactory<FactoryPost>
 */
class FactoryWithoutRepository extends EntityFactory
{
    protected function definition(): FactoryPost
    {
        return new FactoryPost();
    }
}

/**
 * @extends EntityFactory<FactoryPost>
 */
class FactoryWithInvalidRepository extends EntityFactory
{
    protected const string REPOSITORY = FactoryPost::class;

    protected function definition(): FactoryPost
    {
        return new FactoryPost();
    }
}

class FactoryInsertConnection implements ConnectionInterface
{
    /** @var list<string> */
    public array $executed = [];

    private int $nextId = 0;

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
        return [];
    }

    public function execute(
        string $sql,
        array $bindings = [],
    ): int {
        $this->executed[] = $sql;
        $this->nextId++;

        return 1;
    }

    public function prepare(
        string $sql,
    ): StatementInterface {
        throw new RuntimeException('Not implemented');
    }

    public function lastInsertId(): int
    {
        return $this->nextId;
    }

    public function driverName(): string
    {
        return 'pgsql';
    }

    public function supportsReturning(): bool
    {
        return true;
    }

    public function quoteIdentifier(
        string $identifier,
    ): string {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }
}

/**
 * @return array{factory: FactoryPostFactory, connection: FactoryInsertConnection, events: FakeEventDispatcher}
 */
function makeFactoryWithRepository(): array
{
    $connection = new FactoryInsertConnection();
    $events = new FakeEventDispatcher();
    $container = new Container();
    $container->instance(FactoryPostRepository::class, new FactoryPostRepository(
        $connection,
        new EntityMetadataFactory(),
        new EntityHydrator(),
        null,
        $events,
    ));

    return [
        'factory' => new FactoryPostFactory($container),
        'connection' => $connection,
        'events' => $events,
    ];
}

describe('EntityFactory', function (): void {
    it('makes an entity from the definition', function (): void {
        $post = new FactoryPostFactory()->make();

        expect($post)->toBeInstanceOf(FactoryPost::class)
            ->and($post->title)->toBe('Post 1')
            ->and($post->id)->toBeNull();
    });

    it('applies state closures in order after the definition', function (): void {
        $post = new FactoryPostFactory()->make(
            fn (FactoryPost $post) => $post->status = 'live',
            fn (FactoryPost $post) => $post->title = $post->title . ' (' . $post->status . ')',
        );

        expect($post->title)->toBe('Post 1 (live)')
            ->and($post->status)->toBe('live');
    });

    it('makes a list of entities with makeMany', function (): void {
        $posts = new FactoryPostFactory()->makeMany(3, fn (FactoryPost $post) => $post->status = 'live');

        expect($posts)->toHaveCount(3)
            ->and(array_column($posts, 'title'))->toBe(['Post 1', 'Post 2', 'Post 3'])
            ->and(array_column($posts, 'status'))->toBe(['live', 'live', 'live']);
    });

    it('calls definition once per entity so makeMany returns distinct instances', function (): void {
        [$first, $second] = new FactoryPostFactory()->makeMany(2);

        expect($first)->not->toBe($second);
    });

    it('returns no entities when makeMany is asked for zero', function (): void {
        expect(new FactoryPostFactory()->makeMany(0))->toBeEmpty();
    });

    it('throws when makeMany is asked for a negative count', function (): void {
        expect(fn () => new FactoryPostFactory()->makeMany(-1))
            ->toThrow(EntityFactoryException::class, 'cannot build -1 entities');
    });

    it('cycles sequence states across the entities it builds', function (): void {
        $posts = new FactoryPostFactory()
            ->sequence(
                fn (FactoryPost $post) => $post->status = 'draft',
                fn (FactoryPost $post) => $post->status = 'live',
            )
            ->makeMany(5);

        expect(array_column($posts, 'status'))->toBe(['draft', 'live', 'draft', 'live', 'draft']);
    });

    it('applies explicit states after the sequence state', function (): void {
        $post = new FactoryPostFactory()
            ->sequence(fn (FactoryPost $post) => $post->status = 'draft')
            ->make(fn (FactoryPost $post) => $post->status = 'archived');

        expect($post->status)->toBe('archived');
    });

    it('leaves the original factory without the sequence', function (): void {
        $factory = new FactoryPostFactory();
        $factory->sequence(fn (FactoryPost $post) => $post->status = 'live');

        expect($factory->make()->status)->toBe('draft');
    });

    it('throws when sequence is given no states', function (): void {
        expect(fn () => new FactoryPostFactory()->sequence())
            ->toThrow(EntityFactoryException::class, 'needs at least one state');
    });

    it('creates an entity through the repository so lifecycle events fire', function (): void {
        ['factory' => $factory, 'connection' => $connection, 'events' => $events] = makeFactoryWithRepository();

        $post = $factory->create(fn (FactoryPost $post) => $post->status = 'live');

        expect($post->id)->toBe(1)
            ->and($post->status)->toBe('live')
            ->and($connection->executed)->toHaveCount(1)
            ->and($connection->executed[0])->toContain('INSERT INTO "factory_posts"');
        $events->assertDispatched(EntityCreating::class);
        $events->assertDispatched(EntityCreated::class);
    });

    it('creates a list of persisted entities with createMany', function (): void {
        ['factory' => $factory, 'events' => $events] = makeFactoryWithRepository();

        $posts = $factory->createMany(2);

        expect(array_column($posts, 'id'))->toBe([1, 2]);
        $events->assertDispatchedCount(EntityCreated::class, 2);
    });

    it('throws when create is used without a container', function (): void {
        expect(fn () => new FactoryPostFactory()->create())
            ->toThrow(EntityFactoryException::class, 'has no container');
    });

    it('throws when REPOSITORY is not set', function (): void {
        expect(fn () => new FactoryWithoutRepository(new Container())->create())
            ->toThrow(EntityFactoryException::class, 'does not name a repository');
    });

    it('throws when REPOSITORY is not a repository', function (): void {
        expect(fn () => new FactoryWithInvalidRepository(new Container())->create())
            ->toThrow(EntityFactoryException::class, 'does not name a repository');
    });
});
