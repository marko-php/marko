<?php

declare(strict_types=1);

use Marko\Database\Query\EntityQueryBuilderInterface;
use Marko\Database\Query\QueryBuilderInterface;
use Marko\Scope\Attributes\Scoped;
use Marko\Scope\Axis\ScopeAxis;
use Marko\Scope\Context\ScopeContext;
use Marko\Scope\Exceptions\ScopeContextException;
use Marko\Scope\Hierarchy\ScopeHierarchy;
use Marko\Scope\Metadata\ScopeMetadataFactory;
use Marko\Scope\Query\ScopedOrderBy;
use Marko\Scope\Query\ScopeSortExpression;
use Marko\Scope\Query\ScopeSortRendererInterface;
use Marko\Scope\Registry\ScopeRegistryInterface;

// ─── Fixtures ────────────────────────────────────────────────────────────────

class ScopedOrderByProduct
{
    #[Scoped(axes: ['store'])]
    public string $name = '';

    public string $sku = '';
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

function makeScopedOrderByRegistry(array $axes = []): ScopeRegistryInterface
{
    return new class ($axes) implements ScopeRegistryInterface
    {
        /** @var array<string, ScopeAxis> */
        private array $builtAxes;

        public function __construct(private readonly array $axes)
        {
            $this->builtAxes = [];
            foreach ($axes as $name => $paths) {
                $hierarchy = new ScopeHierarchy($paths);
                $this->builtAxes[$name] = new ScopeAxis(name: $name, hierarchy: $hierarchy);
            }
        }

        public function hasAxis(string $name): bool
        {
            return isset($this->builtAxes[$name]);
        }

        public function getAxis(string $name): ScopeAxis
        {
            return $this->builtAxes[$name];
        }

        public function listAxes(): array
        {
            return array_keys($this->builtAxes);
        }

        public function getHierarchy(string $axisName): ScopeHierarchy
        {
            return $this->builtAxes[$axisName]->hierarchy;
        }
    };
}

function makeBuilderSpy(): EntityQueryBuilderInterface
{
    return new class () implements EntityQueryBuilderInterface
    {
        public array $orderByCalls = [];

        public array $orderByRawCalls = [];

        public function with(string ...$relations): static
        {
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

        public function distinct(): static
        {
            return $this;
        }

        public function where(
            string $column,
            string $operator,
            mixed $value,
        ): static
        {
            return $this;
        }

        public function whereIn(
            string $column,
            array $values,
        ): static
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

        public function whereJsonContains(
            string $path,
            mixed $value,
        ): static
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
        ): static
        {
            return $this;
        }

        public function join(
            string $table,
            string $first,
            string $operator,
            string $second,
        ): static
        {
            return $this;
        }

        public function leftJoin(
            string $table,
            string $first,
            string $operator,
            string $second,
        ): static
        {
            return $this;
        }

        public function rightJoin(
            string $table,
            string $first,
            string $operator,
            string $second,
        ): static
        {
            return $this;
        }

        public function groupBy(string ...$columns): static
        {
            return $this;
        }

        public function having(
            string $expression,
            array $bindings = [],
        ): static
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

        public function limit(int $limit): static
        {
            return $this;
        }

        public function offset(int $offset): static
        {
            return $this;
        }

        public function getColumnCount(): int
        {
            return 0;
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

        public function raw(
            string $sql,
            array $bindings = [],
        ): array
        {
            return [];
        }

        public function orderBy(
            string $column,
            string $direction = 'ASC',
        ): static
        {
            $this->orderByCalls[] = ['column' => $column, 'direction' => $direction];

            return $this;
        }

        public function orderByRaw(
            string $expression,
            string $direction = 'ASC',
        ): static
        {
            $this->orderByRawCalls[] = ['expression' => $expression, 'direction' => $direction];

            return $this;
        }
    };
}

function makeRenderer(string $sql = 'COALESCE(json_extract(scopes, \'$.store.en\'), name)'): ScopeSortRendererInterface
{
    return new readonly class ($sql) implements ScopeSortRendererInterface
    {
        public function __construct(private string $sql) {}

        public function render(ScopeSortExpression $expression): string
        {
            return $this->sql;
        }
    };
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('accepts ScopeMetadataFactory, ScopeContext, and ScopeSortRendererInterface in constructor', function (): void {
    $registry = makeScopedOrderByRegistry(['store' => ['en', 'en.gb']]);
    $factory = new ScopeMetadataFactory($registry);
    $context = new ScopeContext($registry);
    $renderer = makeRenderer();

    $spec = new ScopedOrderBy(
        property: 'name',
        direction: 'desc',
        scopeMetadataFactory: $factory,
        scopeContext: $context,
        scopeSortRenderer: $renderer,
        entityClass: ScopedOrderByProduct::class,
    );

    expect($spec->property)->toBe('name')
        ->and($spec->direction)->toBe('desc');
});

it('accepts a property name and optional direction defaulting to asc', function (): void {
    $registry = makeScopedOrderByRegistry(['store' => ['en', 'en.gb']]);
    $factory = new ScopeMetadataFactory($registry);
    $context = new ScopeContext($registry);
    $renderer = makeRenderer();

    $spec = new ScopedOrderBy(
        property: 'name',
        scopeMetadataFactory: $factory,
        scopeContext: $context,
        scopeSortRenderer: $renderer,
        entityClass: ScopedOrderByProduct::class,
    );

    expect($spec->property)->toBe('name')
        ->and($spec->direction)->toBe('asc');
});

it('calls orderByRaw with the renderer-generated COALESCE expression when scope is active', function (): void {
    $registry = makeScopedOrderByRegistry(['store' => ['en', 'en.gb']]);
    $factory = new ScopeMetadataFactory($registry);
    $context = new ScopeContext($registry);
    $context->in('store', 'en.gb');

    $sql = "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(`scopes`, '$.store.en.gb')), name)";
    $renderer = makeRenderer($sql);

    $spec = new ScopedOrderBy(
        property: 'name',
        scopeMetadataFactory: $factory,
        scopeContext: $context,
        scopeSortRenderer: $renderer,
        entityClass: ScopedOrderByProduct::class,
    );

    $builder = makeBuilderSpy();
    $spec->apply($builder);

    expect($builder->orderByRawCalls)->toHaveCount(1)
        ->and($builder->orderByRawCalls[0]['expression'])->toBe($sql)
        ->and($builder->orderByCalls)->toBeEmpty();
});

it('falls back to plain orderBy when no scope is active for any of the property\'s axes', function (): void {
    $registry = makeScopedOrderByRegistry(['store' => ['en', 'en.gb']]);
    $factory = new ScopeMetadataFactory($registry);
    $context = new ScopeContext($registry);
    // No scope set on context
    $renderer = makeRenderer();

    $spec = new ScopedOrderBy(
        property: 'name',
        scopeMetadataFactory: $factory,
        scopeContext: $context,
        scopeSortRenderer: $renderer,
        entityClass: ScopedOrderByProduct::class,
    );

    $builder = makeBuilderSpy();
    $spec->apply($builder);

    expect($builder->orderByCalls)->toHaveCount(1)
        ->and($builder->orderByCalls[0]['column'])->toBe('name')
        ->and($builder->orderByRawCalls)->toBeEmpty();
});

it('throws ScopeContextException when the property is not Scoped', function (): void {
    $registry = makeScopedOrderByRegistry(['store' => ['en', 'en.gb']]);
    $factory = new ScopeMetadataFactory($registry);
    $context = new ScopeContext($registry);
    $renderer = makeRenderer();

    $spec = new ScopedOrderBy(
        property: 'sku',
        scopeMetadataFactory: $factory,
        scopeContext: $context,
        scopeSortRenderer: $renderer,
        entityClass: ScopedOrderByProduct::class,
    );

    $builder = makeBuilderSpy();
    expect(fn () => $spec->apply($builder))->toThrow(ScopeContextException::class);
});

it(
    'preserves direction asc or desc on the emitted ORDER BY',
    function (string $direction, string $expectedDirectionUpper): void {
        $registry = makeScopedOrderByRegistry(['store' => ['en', 'en.gb']]);
        $factory = new ScopeMetadataFactory($registry);
        $context = new ScopeContext($registry);
        $context->in('store', 'en');
        $renderer = makeRenderer('COALESCE(expr)');
    
        $spec = new ScopedOrderBy(
            property: 'name',
            scopeMetadataFactory: $factory,
            scopeContext: $context,
            scopeSortRenderer: $renderer,
            entityClass: ScopedOrderByProduct::class,
            direction: $direction,
        );
    
        $builder = makeBuilderSpy();
        $spec->apply($builder);
    
        expect($builder->orderByRawCalls)->toHaveCount(1)
            ->and($builder->orderByRawCalls[0]['direction'])->toBe($expectedDirectionUpper);
    }
)->with([
    ['asc', 'ASC'],
    ['desc', 'DESC'],
]);

it('builds a ScopeSortExpression by reading ScopeMetadata for the entity class on apply', function (): void {
    $registry = makeScopedOrderByRegistry(['store' => ['en', 'en.gb']]);
    $factory = new ScopeMetadataFactory($registry);
    $context = new ScopeContext($registry);
    $context->in('store', 'en.gb');

    $renderer = new class () implements ScopeSortRendererInterface
    {
        public ?ScopeSortExpression $captured = null;

        public function render(ScopeSortExpression $expression): string
        {
            $this->captured = $expression;

            return 'COALESCE(expr)';
        }
    };

    $spec = new ScopedOrderBy(
        property: 'name',
        scopeMetadataFactory: $factory,
        scopeContext: $context,
        scopeSortRenderer: $renderer,
        entityClass: ScopedOrderByProduct::class,
    );

    $builder = makeBuilderSpy();
    $spec->apply($builder);

    expect($renderer->captured)->toBeInstanceOf(ScopeSortExpression::class)
        ->and($renderer->captured->property)->toBe('name')
        ->and($renderer->captured->column)->toBe('name');
});
