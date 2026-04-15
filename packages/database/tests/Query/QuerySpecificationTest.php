<?php

declare(strict_types=1);

use Marko\Database\Query\QueryBuilderInterface;
use Marko\Database\Query\QuerySpecification;

describe('QuerySpecification', function (): void {
    it('defines apply method accepting QueryBuilderInterface', function (): void {
        $reflection = new ReflectionClass(QuerySpecification::class);

        expect($reflection->isInterface())->toBeTrue()
            ->and($reflection->hasMethod('apply'))->toBeTrue();

        $method = $reflection->getMethod('apply');
        $params = $method->getParameters();

        expect($params)->toHaveCount(1)
            ->and($params[0]->getName())->toBe('builder')
            ->and($params[0]->getType()?->getName())->toBe(QueryBuilderInterface::class);

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

            public function where(string $column, string $operator, mixed $value): static
            {
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

            public function whereIn(string $column, array $values): static
            {
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

            public function orWhere(string $column, string $operator, mixed $value): static
            {
                return $this;
            }

            public function join(string $table, string $first, string $operator, string $second): static
            {
                return $this;
            }

            public function leftJoin(string $table, string $first, string $operator, string $second): static
            {
                return $this;
            }

            public function rightJoin(string $table, string $first, string $operator, string $second): static
            {
                return $this;
            }

            public function orderBy(string $column, string $direction = 'ASC'): static
            {
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

            public function count(): int
            {
                return 0;
            }

            public function raw(string $sql, array $bindings = []): array
            {
                return [];
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

            public function where(string $column, string $operator, mixed $value): static
            {
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

            public function whereIn(string $column, array $values): static
            {
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

            public function orWhere(string $column, string $operator, mixed $value): static
            {
                return $this;
            }

            public function join(string $table, string $first, string $operator, string $second): static
            {
                return $this;
            }

            public function leftJoin(string $table, string $first, string $operator, string $second): static
            {
                return $this;
            }

            public function rightJoin(string $table, string $first, string $operator, string $second): static
            {
                return $this;
            }

            public function orderBy(string $column, string $direction = 'ASC'): static
            {
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

            public function count(): int
            {
                return 0;
            }

            public function raw(string $sql, array $bindings = []): array
            {
                return [];
            }
        };

        $statusSpec->apply($builder);

        expect($calledWith)->toBe(['status', '=', 'published']);
    });
});
