<?php

declare(strict_types=1);

use Marko\Database\Query\EntityQueryBuilderInterface;
use Marko\Database\Query\QueryBuilderInterface;
use Marko\Database\Query\QuerySpecification;

describe('QuerySpecification', function (): void {
    it('defines apply method accepting EntityQueryBuilderInterface', function (): void {
        $reflection = new ReflectionClass(QuerySpecification::class);

        expect($reflection->isInterface())->toBeTrue()
            ->and($reflection->hasMethod('apply'))->toBeTrue();

        $method = $reflection->getMethod('apply');
        $params = $method->getParameters();

        expect($params)->toHaveCount(1)
            ->and($params[0]->getName())->toBe('builder')
            ->and($params[0]->getType()?->getName())->toBe(EntityQueryBuilderInterface::class);

        $returnType = $method->getReturnType();
        expect($returnType?->getName())->toBe('void');
    });

    it('can be implemented with a simple where clause', function (): void {
        $activeSpec = new class () implements QuerySpecification
        {
            public function apply(QueryBuilderInterface $builder): void
            {
                $builder->where('is_active', '=', true);
            }
        };

        $calledWith = null;
        $builder = new class ($calledWith) implements QueryBuilderInterface
        {
            public function __construct(private mixed &$calledWith) {}

            public function where(
                string $column,
                string $operator,
                mixed $value,
            ): static {
                $this->calledWith = [$column, $operator, $value];

                return $this;
            }

            public function table(string $table): static
            {
                return $this;
            }

            public function select(string ...$columns): static
            {
                return $this;
            }

            public function whereIn(
                string $column,
                array $values,
            ): static {
                return $this;
            }

            public function whereNull(string $column): static
            {
                return $this;
            }

            public function whereNotNull(string $column): static
            {
                return $this;
            }

            public function whereJsonContains(string $path, mixed $value): static
            {
                return $this;
            }

            public function whereJsonExists(string $path): static
            {
                return $this;
            }

            public function whereJsonMissing(string $path): static
            {
                return $this;
            }

            public function orWhere(
                string $column,
                string $operator,
                mixed $value,
            ): static {
                return $this;
            }

            public function join(
                string $table,
                string $first,
                string $operator,
                string $second,
            ): static {
                return $this;
            }

            public function leftJoin(
                string $table,
                string $first,
                string $operator,
                string $second,
            ): static {
                return $this;
            }

            public function rightJoin(
                string $table,
                string $first,
                string $operator,
                string $second,
            ): static {
                return $this;
            }

            public function orderBy(
                string $column,
                string $direction = 'ASC',
            ): static {
                return $this;
            }

            public function limit(int $limit): static
            {
                return $this;
            }

            public function offset(int $offset): static
            {
                return $this;
            }

            public function distinct(): static
            {
                return $this;
            }

            public function union(QueryBuilderInterface $other): static
            {
                return $this;
            }

            public function unionAll(QueryBuilderInterface $other): static
            {
                return $this;
            }

            public function getColumnCount(): int
            {
                return 1;
            }

            public function compileSubquery(array &$bindings): string
            {
                return '';
            }

            public function get(): array
            {
                return [];
            }

            public function first(): ?array
            {
                return null;
            }

            public function insert(array $data): int
            {
                return 0;
            }

            public function update(array $data): int
            {
                return 0;
            }

            public function delete(): int
            {
                return 0;
            }

            public function count(?string $column = null): int
            {
                return 0;
            }

            public function raw(
                string $sql,
                array $bindings = [],
            ): array {
                return [];
            }

            public function groupBy(string ...$columns): static
            {
                return $this;
            }

            public function having(string $expression, array $bindings = []): static
            {
                return $this;
            }

            public function min(string $column): int|float|null
            {
                return null;
            }

            public function max(string $column): int|float|null
            {
                return null;
            }

            public function sum(string $column): int|float|null
            {
                return null;
            }

            public function avg(string $column): int|float|null
            {
                return null;
            }
        };

        $activeSpec->apply($builder);

        expect($calledWith)->toBe(['is_active', '=', true]);
    });

    it('can be implemented with constructor parameters for configuration', function (): void {
        $statusSpec = new class ('published') implements QuerySpecification
        {
            public function __construct(private readonly string $status) {}

            public function apply(QueryBuilderInterface $builder): void
            {
                $builder->where('status', '=', $this->status);
            }
        };

        $calledWith = null;
        $builder = new class ($calledWith) implements QueryBuilderInterface
        {
            public function __construct(private mixed &$calledWith) {}

            public function where(
                string $column,
                string $operator,
                mixed $value,
            ): static {
                $this->calledWith = [$column, $operator, $value];

                return $this;
            }

            public function table(string $table): static
            {
                return $this;
            }

            public function select(string ...$columns): static
            {
                return $this;
            }

            public function whereIn(
                string $column,
                array $values,
            ): static {
                return $this;
            }

            public function whereNull(string $column): static
            {
                return $this;
            }

            public function whereNotNull(string $column): static
            {
                return $this;
            }

            public function whereJsonContains(string $path, mixed $value): static
            {
                return $this;
            }

            public function whereJsonExists(string $path): static
            {
                return $this;
            }

            public function whereJsonMissing(string $path): static
            {
                return $this;
            }

            public function orWhere(
                string $column,
                string $operator,
                mixed $value,
            ): static {
                return $this;
            }

            public function join(
                string $table,
                string $first,
                string $operator,
                string $second,
            ): static {
                return $this;
            }

            public function leftJoin(
                string $table,
                string $first,
                string $operator,
                string $second,
            ): static {
                return $this;
            }

            public function rightJoin(
                string $table,
                string $first,
                string $operator,
                string $second,
            ): static {
                return $this;
            }

            public function orderBy(
                string $column,
                string $direction = 'ASC',
            ): static {
                return $this;
            }

            public function limit(int $limit): static
            {
                return $this;
            }

            public function offset(int $offset): static
            {
                return $this;
            }

            public function distinct(): static
            {
                return $this;
            }

            public function union(QueryBuilderInterface $other): static
            {
                return $this;
            }

            public function unionAll(QueryBuilderInterface $other): static
            {
                return $this;
            }

            public function getColumnCount(): int
            {
                return 1;
            }

            public function compileSubquery(array &$bindings): string
            {
                return '';
            }

            public function get(): array
            {
                return [];
            }

            public function first(): ?array
            {
                return null;
            }

            public function insert(array $data): int
            {
                return 0;
            }

            public function update(array $data): int
            {
                return 0;
            }

            public function delete(): int
            {
                return 0;
            }

            public function count(?string $column = null): int
            {
                return 0;
            }

            public function raw(
                string $sql,
                array $bindings = [],
            ): array {
                return [];
            }

            public function groupBy(string ...$columns): static
            {
                return $this;
            }

            public function having(string $expression, array $bindings = []): static
            {
                return $this;
            }

            public function min(string $column): int|float|null
            {
                return null;
            }

            public function max(string $column): int|float|null
            {
                return null;
            }

            public function sum(string $column): int|float|null
            {
                return null;
            }

            public function avg(string $column): int|float|null
            {
                return null;
            }
        };

        $statusSpec->apply($builder);

        expect($calledWith)->toBe(['status', '=', 'published']);
    });
});
