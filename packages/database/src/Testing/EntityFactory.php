<?php

declare(strict_types=1);

namespace Marko\Database\Testing;

use Marko\Core\Container\ContainerInterface;
use Marko\Database\Entity\Entity;
use Marko\Database\Exceptions\EntityFactoryException;
use Marko\Database\Exceptions\RepositoryException;
use Marko\Database\Repository\RepositoryInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Builds entities for tests with plain PHP: definition() returns a constructed
 * entity (new + property assignment), so IDE navigation, static analysis and
 * renames keep working. There are no attribute arrays and no magic.
 *
 * ```php
 * class PostFactory extends EntityFactory
 * {
 *     protected const string REPOSITORY = PostRepository::class;
 *
 *     protected function definition(): Post
 *     {
 *         $post = new Post();
 *         $post->title = 'A post';
 *         $post->status = Status::Draft;
 *
 *         return $post;
 *     }
 * }
 *
 * $post = new PostFactory($container)->create(fn (Post $p) => $p->status = Status::Live);
 * ```
 *
 * create() saves through the repository named by REPOSITORY, resolved from
 * the container, so lifecycle events fire exactly as in production.
 *
 * @template TEntity of Entity
 */
abstract class EntityFactory
{
    /**
     * The repository create() persists through: a class or interface
     * implementing RepositoryInterface, resolved from the container.
     */
    protected const string REPOSITORY = '';

    /** @var list<callable(TEntity): mixed> */
    private array $sequence = [];

    private int $built = 0;

    public function __construct(
        private readonly ?ContainerInterface $container = null,
    ) {}

    /**
     * Build one entity with sensible defaults. Called once per entity.
     *
     * @return TEntity
     */
    abstract protected function definition(): Entity;

    /**
     * Build an entity without saving it. The definition runs first, then the
     * next sequence state (if any), then each state closure in order.
     *
     * @param callable(TEntity): mixed ...$states
     * @return TEntity
     */
    public function make(
        callable ...$states,
    ): Entity {
        $entity = $this->definition();

        if ($this->sequence !== []) {
            $this->sequence[$this->built % count($this->sequence)]($entity);
        }

        $this->built++;

        foreach ($states as $state) {
            $state($entity);
        }

        return $entity;
    }

    /**
     * Build an entity and save it through the repository.
     *
     * @param callable(TEntity): mixed ...$states
     * @return TEntity
     * @throws EntityFactoryException|RepositoryException|ContainerExceptionInterface|NotFoundExceptionInterface
     */
    public function create(
        callable ...$states,
    ): Entity {
        $entity = $this->make(...$states);
        $this->repository()->save($entity);

        return $entity;
    }

    /**
     * Build $count entities without saving them.
     *
     * @param callable(TEntity): mixed ...$states
     * @return list<TEntity>
     * @throws EntityFactoryException
     */
    public function makeMany(
        int $count,
        callable ...$states,
    ): array {
        if ($count < 0) {
            throw EntityFactoryException::invalidCount(static::class, $count);
        }

        $entities = [];

        for ($i = 0; $i < $count; $i++) {
            $entities[] = $this->make(...$states);
        }

        return $entities;
    }

    /**
     * Build $count entities and save each one through the repository.
     *
     * @param callable(TEntity): mixed ...$states
     * @return list<TEntity>
     * @throws EntityFactoryException|RepositoryException|ContainerExceptionInterface|NotFoundExceptionInterface
     */
    public function createMany(
        int $count,
        callable ...$states,
    ): array {
        $repository = $this->repository();
        $entities = $this->makeMany($count, ...$states);

        foreach ($entities as $entity) {
            $repository->save($entity);
        }

        return $entities;
    }

    /**
     * A copy of this factory that applies the given states in turn, one per
     * entity it builds, starting again from the first after the last.
     *
     * @param callable(TEntity): mixed ...$states
     * @return static
     * @throws EntityFactoryException
     */
    public function sequence(
        callable ...$states,
    ): static {
        if ($states === []) {
            throw EntityFactoryException::emptySequence(static::class);
        }

        return clone($this, [
            'sequence' => array_values($states),
            'built' => 0,
        ]);
    }

    /**
     * @return RepositoryInterface<TEntity>
     * @throws EntityFactoryException|ContainerExceptionInterface|NotFoundExceptionInterface
     */
    private function repository(): RepositoryInterface
    {
        if ($this->container === null) {
            throw EntityFactoryException::noContainer(static::class);
        }

        $repository = static::REPOSITORY;

        if (
            $repository === ''
            || !is_a($repository, RepositoryInterface::class, true)
        ) {
            throw EntityFactoryException::invalidRepository(static::class, $repository);
        }

        /** @var RepositoryInterface<TEntity> */
        return $this->container->get($repository);
    }
}
