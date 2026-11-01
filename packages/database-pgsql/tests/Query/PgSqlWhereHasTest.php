<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Tests\Query;

use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Exceptions\RepositoryException;
use Marko\Database\PgSql\Query\PgSqlQueryBuilderFactory;
use Marko\Database\PgSql\Tests\Fixtures\Relationships\RelCategoryRepository;
use Marko\Database\PgSql\Tests\Fixtures\Relationships\RelProjectRepository;
use Marko\Database\PgSql\Tests\Fixtures\Relationships\RelStudio;
use Marko\Database\PgSql\Tests\Fixtures\Relationships\RelStudioRepository;
use Marko\Database\Query\EntityQueryBuilderInterface;
use Marko\Database\Repository\Repository;

/**
 * @template T of Repository
 * @param class-string<T> $repositoryClass
 * @return T
 */
function pgsqlRelationshipRepository(
    string $repositoryClass,
    MockConnection $connection,
): Repository {
    return new $repositoryClass(
        $connection,
        new EntityMetadataFactory(),
        new EntityHydrator(),
        new PgSqlQueryBuilderFactory($connection),
    );
}

describe('PgSqlQueryBuilder whereHas', function (): void {
    beforeEach(function (): void {
        $this->connection = new MockConnection();
        $this->studios = pgsqlRelationshipRepository(RelStudioRepository::class, $this->connection);
    });

    it('compiles a HasMany relationship to a correlated EXISTS on the related foreign key', function (): void {
        $this->studios->query()->whereHas('projects')->get();

        expect($this->connection->lastQuerySql)->toBe(
            'SELECT * FROM "rel_studios" WHERE EXISTS (SELECT * FROM "rel_projects" AS "marko_rel_1" '
            . 'WHERE "marko_rel_1"."studio_id" = "rel_studios"."id")',
        );
    });

    it('compiles a HasOne relationship the same way as HasMany', function (): void {
        $this->studios->query()->whereHas('profile')->get();

        expect($this->connection->lastQuerySql)->toBe(
            'SELECT * FROM "rel_studios" WHERE EXISTS (SELECT * FROM "rel_studio_profiles" AS "marko_rel_1" '
            . 'WHERE "marko_rel_1"."studio_id" = "rel_studios"."id")',
        );
    });

    it('compiles a BelongsTo relationship against the outer foreign key', function (): void {
        $projects = pgsqlRelationshipRepository(RelProjectRepository::class, $this->connection);

        $projects->query()->whereHas('studio')->get();

        expect($this->connection->lastQuerySql)->toBe(
            'SELECT * FROM "rel_projects" WHERE EXISTS (SELECT * FROM "rel_studios" AS "marko_rel_1" '
            . 'WHERE "marko_rel_1"."id" = "rel_projects"."studio_id")',
        );
    });

    it('compiles a BelongsToMany relationship through the pivot table', function (): void {
        $this->studios->query()->whereHas(
            'tags',
            fn (EntityQueryBuilderInterface $q) => $q->where('label', '=', 'vip'),
        )->get();

        expect($this->connection->lastQuerySql)->toBe(
            'SELECT * FROM "rel_studios" WHERE EXISTS (SELECT * FROM "rel_tags" AS "marko_rel_1" '
            . 'WHERE "label" = ? AND EXISTS (SELECT * FROM "rel_studio_tags" AS "marko_rel_2" '
            . 'WHERE "marko_rel_2"."studio_id" = "rel_studios"."id" AND "marko_rel_2"."tag_id" = "marko_rel_1"."id"))',
        )->and($this->connection->lastQueryBindings)->toBe(['vip']);
    });

    it('applies a constraint, including whereNotIn, inside the subquery', function (): void {
        $this->studios->query()
            ->where('name', '=', 'Acme')
            ->whereHas(
                'projects',
                fn (EntityQueryBuilderInterface $q) => $q->whereNotIn('status', ['archived', 'draft']),
            )
            ->get();

        expect($this->connection->lastQuerySql)->toBe(
            'SELECT * FROM "rel_studios" WHERE "name" = ? AND EXISTS (SELECT * FROM "rel_projects" AS "marko_rel_1" '
            . 'WHERE "status" NOT IN (?, ?) AND "marko_rel_1"."studio_id" = "rel_studios"."id")',
        )->and($this->connection->lastQueryBindings)->toBe(['Acme', 'archived', 'draft']);
    });

    it('compiles whereDoesntHave to NOT EXISTS', function (): void {
        $this->studios->query()
            ->whereDoesntHave('projects', fn (EntityQueryBuilderInterface $q) => $q->where('status', '=', 'active'))
            ->get();

        expect($this->connection->lastQuerySql)->toBe(
            'SELECT * FROM "rel_studios" WHERE NOT EXISTS (SELECT * FROM "rel_projects" AS "marko_rel_1" '
            . 'WHERE "status" = ? AND "marko_rel_1"."studio_id" = "rel_studios"."id")',
        )->and($this->connection->lastQueryBindings)->toBe(['active']);
    });

    it('compiles a dotted path to nested EXISTS with the constraint on the last relationship', function (): void {
        $projects = pgsqlRelationshipRepository(RelProjectRepository::class, $this->connection);

        $projects->query()
            ->whereHas('studio.tags', fn (EntityQueryBuilderInterface $q) => $q->where('label', '=', 'vip'))
            ->get();

        expect($this->connection->lastQuerySql)->toBe(
            'SELECT * FROM "rel_projects" WHERE EXISTS (SELECT * FROM "rel_studios" AS "marko_rel_1" '
            . 'WHERE "marko_rel_1"."id" = "rel_projects"."studio_id" AND EXISTS (SELECT * FROM "rel_tags" AS "marko_rel_2" '
            . 'WHERE "label" = ? AND EXISTS (SELECT * FROM "rel_studio_tags" AS "marko_rel_3" '
            . 'WHERE "marko_rel_3"."studio_id" = "marko_rel_1"."id" AND "marko_rel_3"."tag_id" = "marko_rel_2"."id")))',
        )->and($this->connection->lastQueryBindings)->toBe(['vip']);
    });

    it('lets a constraint call whereHas again on the related entity', function (): void {
        $projects = pgsqlRelationshipRepository(RelProjectRepository::class, $this->connection);

        $projects->query()
            ->whereHas('studio', fn (EntityQueryBuilderInterface $q) => $q->whereHas('profile'))
            ->get();

        expect($this->connection->lastQuerySql)->toBe(
            'SELECT * FROM "rel_projects" WHERE EXISTS (SELECT * FROM "rel_studios" AS "marko_rel_1" '
            . 'WHERE "marko_rel_1"."id" = "rel_projects"."studio_id" AND EXISTS (SELECT * FROM "rel_studio_profiles" '
            . 'AS "marko_rel_2" WHERE "marko_rel_2"."studio_id" = "marko_rel_1"."id"))',
        );
    });

    it('aliases a self-referential relationship so parent and child never collide', function (): void {
        $categories = pgsqlRelationshipRepository(RelCategoryRepository::class, $this->connection);

        $categories->query()->whereHas('children')->whereDoesntHave('parent')->get();

        expect($this->connection->lastQuerySql)->toBe(
            'SELECT * FROM "rel_categories" WHERE EXISTS (SELECT * FROM "rel_categories" AS "marko_rel_1" '
            . 'WHERE "marko_rel_1"."parent_id" = "rel_categories"."id") AND NOT EXISTS (SELECT * FROM "rel_categories" '
            . 'AS "marko_rel_2" WHERE "marko_rel_2"."id" = "rel_categories"."parent_id")',
        );
    });

    it('correlates with the outer alias when the outer table is aliased', function (): void {
        $this->studios->query()->table('rel_studios', 's')->whereHas('projects')->get();

        expect($this->connection->lastQuerySql)->toBe(
            'SELECT * FROM "rel_studios" AS "s" WHERE EXISTS (SELECT * FROM "rel_projects" AS "marko_rel_1" '
            . 'WHERE "marko_rel_1"."studio_id" = "s"."id")',
        );
    });

    it('applies the same filter to count()', function (): void {
        $this->studios->query()->whereHas('projects')->count();

        expect($this->connection->lastQuerySql)->toBe(
            'SELECT COUNT(*) as aggregate FROM "rel_studios" WHERE EXISTS (SELECT * FROM "rel_projects" AS "marko_rel_1" '
            . 'WHERE "marko_rel_1"."studio_id" = "rel_studios"."id")',
        );
    });

    it('throws RepositoryException listing the available relationships for an unknown relation', function (): void {
        expect(fn () => $this->studios->query()->whereHas('owner'))->toThrow(
            RepositoryException::class,
            "Entity '" . RelStudio::class . "' does not have a relationship named 'owner'. "
            . 'Available relationships: profile, projects, tags.',
        );
    });

    it('validates every segment of a dotted path, not just the first', function (): void {
        $projects = pgsqlRelationshipRepository(RelProjectRepository::class, $this->connection);

        expect(fn () => $projects->query()->whereDoesntHave('studio.owner'))->toThrow(
            RepositoryException::class,
            "Entity '" . RelStudio::class . "' does not have a relationship named 'owner'. "
            . 'Available relationships: profile, projects, tags.',
        );
    });
});
