<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Tests\Integration;

use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\PgSql\Connection\PgSqlConnection;
use Marko\Database\PgSql\Query\PgSqlQueryBuilderFactory;
use Marko\Database\PgSql\Tests\Fixtures\IntegrationDatabase;
use Marko\Database\PgSql\Tests\Fixtures\Relationships\RelCategoryRepository;
use Marko\Database\PgSql\Tests\Fixtures\Relationships\RelProjectRepository;
use Marko\Database\PgSql\Tests\Fixtures\Relationships\RelStudioRepository;
use Marko\Database\Query\EntityQueryBuilderInterface;
use Marko\Database\Repository\Repository;
use Marko\Database\Repository\RepositoryQueryBuilder;

/*
 * whereHas() / whereDoesntHave() against a real PostgreSQL server: every
 * relationship type, constraints, nested paths, a self-referential tree, and
 * no duplicate parents on has-many. Uses the MARKO_TEST_PGSQL_* variables and
 * skips when they are unset (fails with MARKO_INTEGRATION_REQUIRED set).
 * Creates and drops the rel_* tables. Part of the integration-services group.
 */

const PGSQL_WHERE_HAS_TABLES = [
    'rel_studio_tags',
    'rel_tags',
    'rel_projects',
    'rel_studio_profiles',
    'rel_studios',
    'rel_categories',
];

/**
 * @return list<int>
 */
function pgsqlWhereHasIds(
    RepositoryQueryBuilder $query,
): array {
    return array_map(
        static fn (Entity $entity): int => $entity->id,
        $query->orderBy('id')->getEntities()->toArray(),
    );
}

/**
 * @template T of Repository
 * @param class-string<T> $repositoryClass
 * @return T
 */
function pgsqlWhereHasRepository(
    string $repositoryClass,
    PgSqlConnection $connection,
): Repository {
    return new $repositoryClass(
        $connection,
        new EntityMetadataFactory(),
        new EntityHydrator(),
        new PgSqlQueryBuilderFactory($connection),
    );
}

pest()->group('integration-services');

beforeEach(function (): void {
    $config = IntegrationDatabase::config();

    if ($config === null) {
        $this->markTestSkipped(IntegrationDatabase::SKIP_REASON);
    }

    $this->connection = new PgSqlConnection($config);

    foreach (PGSQL_WHERE_HAS_TABLES as $table) {
        $this->connection->execute("DROP TABLE IF EXISTS $table");
    }

    $this->connection->execute('CREATE TABLE rel_studios (id INT PRIMARY KEY, name VARCHAR(50) NOT NULL)');
    $this->connection->execute(
        'CREATE TABLE rel_studio_profiles (id INT PRIMARY KEY, studio_id INT NOT NULL, verified BOOLEAN NOT NULL)',
    );
    $this->connection->execute(
        'CREATE TABLE rel_projects (id INT PRIMARY KEY, studio_id INT NOT NULL, status VARCHAR(20) NOT NULL)',
    );
    $this->connection->execute('CREATE TABLE rel_tags (id INT PRIMARY KEY, label VARCHAR(20) NOT NULL)');
    $this->connection->execute(
        'CREATE TABLE rel_studio_tags (id INT PRIMARY KEY, studio_id INT NOT NULL, tag_id INT NOT NULL)',
    );
    $this->connection->execute(
        'CREATE TABLE rel_categories (id INT PRIMARY KEY, parent_id INT NULL, name VARCHAR(20) NOT NULL)',
    );

    $this->connection->execute("INSERT INTO rel_studios VALUES (1, 'Acme'), (2, 'Beta'), (3, 'Gamma')");
    $this->connection->execute('INSERT INTO rel_studio_profiles VALUES (1, 1, TRUE)');
    $this->connection->execute(
        "INSERT INTO rel_projects VALUES (1, 1, 'active'), (2, 1, 'active'), (3, 1, 'archived'), (4, 2, 'archived')",
    );
    $this->connection->execute("INSERT INTO rel_tags VALUES (1, 'vip'), (2, 'new')");
    $this->connection->execute('INSERT INTO rel_studio_tags VALUES (1, 1, 1), (2, 2, 2)');
    $this->connection->execute(
        "INSERT INTO rel_categories VALUES (1, NULL, 'root'), (2, 1, 'child'), (3, 1, 'leaf'), (4, 2, 'grandchild')",
    );

    $this->studios = pgsqlWhereHasRepository(RelStudioRepository::class, $this->connection);
    $this->projects = pgsqlWhereHasRepository(RelProjectRepository::class, $this->connection);
    $this->categories = pgsqlWhereHasRepository(RelCategoryRepository::class, $this->connection);
});

afterEach(function (): void {
    if (isset($this->connection)) {
        foreach (PGSQL_WHERE_HAS_TABLES as $table) {
            $this->connection->execute("DROP TABLE IF EXISTS $table");
        }

        $this->connection->disconnect();
    }
});

describe('PostgreSQL whereHas', function (): void {
    it('returns each parent once on a has-many relationship', function (): void {
        expect(pgsqlWhereHasIds($this->studios->query()->whereHas('projects')))->toBe([1, 2])
            ->and($this->studios->query()->whereHas('projects')->count())->toBe(2);
    });

    it('filters on a constrained has-many subquery with whereNotIn', function (): void {
        $query = $this->studios->query()
            ->whereHas('projects', fn (EntityQueryBuilderInterface $q) => $q->whereNotIn('status', ['archived']));

        expect(pgsqlWhereHasIds($query))->toBe([1]);
    });

    it('keeps parents without related rows with whereDoesntHave', function (): void {
        expect(pgsqlWhereHasIds($this->studios->query()->whereDoesntHave('projects')))->toBe([3]);
    });

    it('filters on HasOne, BelongsTo and BelongsToMany relationships', function (): void {
        expect(pgsqlWhereHasIds($this->studios->query()->whereHas('profile')))->toBe([1])
            ->and(pgsqlWhereHasIds($this->projects->query()->whereHas(
                'studio',
                fn (EntityQueryBuilderInterface $q) => $q->where('name', '=', 'Beta'),
            )))->toBe([4])
            ->and(pgsqlWhereHasIds($this->studios->query()->whereHas(
                'tags',
                fn (EntityQueryBuilderInterface $q) => $q->where('label', '=', 'vip'),
            )))->toBe([1]);
    });

    it('filters on a nested dotted path', function (): void {
        $query = $this->projects->query()
            ->whereHas('studio.tags', fn (EntityQueryBuilderInterface $q) => $q->where('label', '=', 'new'));

        expect(pgsqlWhereHasIds($query))->toBe([4]);
    });

    it('filters a self-referential relationship', function (): void {
        expect(pgsqlWhereHasIds($this->categories->query()->whereHas('children')))->toBe([1, 2])
            ->and(pgsqlWhereHasIds($this->categories->query()->whereDoesntHave('parent')))->toBe([1])
            ->and(pgsqlWhereHasIds($this->categories->query()->whereHas('parent.parent')))->toBe([4]);
    });
});
