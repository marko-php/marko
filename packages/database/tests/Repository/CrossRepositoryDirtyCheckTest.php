<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Repository;

use Marko\Clock\SystemClock;
use Marko\Core\Container\BindingRegistry;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Event\Event;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Core\Module\ModuleManifest;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Entity\Entity;
use Marko\Database\Query\QueryBuilderFactoryInterface;
use Marko\Database\Query\QueryBuilderInterface;
use Marko\Database\Repository\Repository;
use Psr\Clock\ClockInterface;
use RuntimeException;

#[Table('cross_repo_articles')]
class CrossRepoArticle extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column]
    public string $title;

    #[Column]
    public string $body;
}

/**
 * @extends Repository<CrossRepoArticle>
 */
class CrossRepoArticleRepository extends Repository
{
    protected const string ENTITY_CLASS = CrossRepoArticle::class;
}

/**
 * A second repository for the same entity, as a separate service would have.
 *
 * @extends Repository<CrossRepoArticle>
 */
class CrossRepoArticlePublisherRepository extends Repository
{
    protected const string ENTITY_CLASS = CrossRepoArticle::class;
}

class CrossRepoRecordingConnection implements ConnectionInterface
{
    /** @var list<array{sql: string, bindings: array<int|string, mixed>}> */
    public array $executed = [];

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
        return [['id' => 7, 'title' => 'Draft title', 'body' => 'Original body']];
    }

    public function execute(
        string $sql,
        array $bindings = [],
    ): int {
        $this->executed[] = ['sql' => $sql, 'bindings' => $bindings];

        return 1;
    }

    public function prepare(
        string $sql,
    ): StatementInterface {
        throw new RuntimeException('Not implemented');
    }

    public function lastInsertId(): int
    {
        return 99;
    }

    public function driverName(): string
    {
        return 'test';
    }

    public function supportsReturning(): bool
    {
        return false;
    }

    public function quoteIdentifier(
        string $identifier,
    ): string {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }
}

/**
 * Container built from the real marko/database module manifest, with the
 * connection supplied the way a driver module would supply it.
 */
function buildCrossRepositoryContainer(ConnectionInterface $connection): Container
{
    $moduleConfig = require dirname(__DIR__, 2) . '/module.php';

    $container = new Container();
    $container->instance(ContainerInterface::class, $container);
    $container->instance(ConnectionInterface::class, $connection);
    $container->instance(ClockInterface::class, new SystemClock());
    $container->instance(EventDispatcherInterface::class, new class () implements EventDispatcherInterface
    {
        public function dispatch(Event $event): void {}
    });
    // A driver module binds this; find() and save() never touch it.
    $container->instance(QueryBuilderFactoryInterface::class, new class () implements QueryBuilderFactoryInterface
    {
        public function create(): QueryBuilderInterface
        {
            throw new RuntimeException('Not used by find() or save()');
        }
    });

    new BindingRegistry($container)->registerModule(new ModuleManifest(
        name: 'marko/database',
        version: '1.0.0',
        bindings: $moduleConfig['bindings'],
        singletons: $moduleConfig['singletons'],
        arguments: $moduleConfig['arguments'] ?? [],
        source: 'vendor',
    ));

    return $container;
}

describe('Cross-repository dirty checking', function (): void {
    it('saves an entity loaded by another repository as an update of only the dirty columns', function (): void {
        $connection = new CrossRepoRecordingConnection();
        $container = buildCrossRepositoryContainer($connection);

        $article = $container->get(CrossRepoArticleRepository::class)->find(7);
        $article->title = 'Published title';

        $container->get(CrossRepoArticlePublisherRepository::class)->save($article);

        expect($connection->executed)->toHaveCount(1)
            ->and($connection->executed[0]['sql'])->toBe('UPDATE "cross_repo_articles" SET "title" = ? WHERE "id" = ?')
            ->and($connection->executed[0]['bindings'])->toBe(['Published title', 7]);
    });
});
