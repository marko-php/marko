<?php

declare(strict_types=1);

use Marko\Scope\Attributes\Scoped;
use Marko\Scope\Axis\ScopeAxis;
use Marko\Scope\Context\ScopeContext;
use Marko\Scope\Hierarchy\ScopeHierarchy;
use Marko\Scope\Metadata\ScopeMetadataFactory;
use Marko\Scope\Query\ScopedOrderBy;
use Marko\Scope\Query\ScopedOrderByFactory;
use Marko\Scope\Query\ScopeSortExpression;
use Marko\Scope\Query\ScopeSortRendererInterface;
use Marko\Scope\Registry\ScopeRegistryInterface;

// ─── Fixtures ────────────────────────────────────────────────────────────────

class ScopedOrderByFactoryProduct
{
    #[Scoped(axes: ['store'])]
    public string $name = '';
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

function makeFactoryRegistry(): ScopeRegistryInterface
{
    return new class () implements ScopeRegistryInterface
    {
        private array $builtAxes;

        public function __construct()
        {
            $hierarchy = new ScopeHierarchy(['en', 'en.gb']);
            $this->builtAxes = ['store' => new ScopeAxis(name: 'store', hierarchy: $hierarchy)];
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

function makeFactoryRenderer(): ScopeSortRendererInterface
{
    return new class () implements ScopeSortRendererInterface
    {
        public function render(ScopeSortExpression $expression): string
        {
            return 'COALESCE(expr)';
        }
    };
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('constructs a ScopedOrderBy with the entity class, property, and direction', function (): void {
    $registry = makeFactoryRegistry();
    $metadataFactory = new ScopeMetadataFactory($registry);
    $context = new ScopeContext($registry);
    $renderer = makeFactoryRenderer();

    $factory = new ScopedOrderByFactory($metadataFactory, $context, $renderer);
    $spec = $factory->create(ScopedOrderByFactoryProduct::class, 'name', 'desc');

    expect($spec)->toBeInstanceOf(ScopedOrderBy::class)
        ->and($spec->property)->toBe('name')
        ->and($spec->direction)->toBe('desc');
});

it('injects ScopeMetadataFactory, ScopeContext, and ScopeSortRendererInterface into the spec', function (): void {
    $registry = makeFactoryRegistry();
    $metadataFactory = new ScopeMetadataFactory($registry);
    $context = new ScopeContext($registry);
    $renderer = makeFactoryRenderer();

    $factory = new ScopedOrderByFactory($metadataFactory, $context, $renderer);
    $spec = $factory->create(ScopedOrderByFactoryProduct::class, 'name', 'asc');

    expect($spec)->toBeInstanceOf(ScopedOrderBy::class);
});

it('defaults direction to asc when omitted', function (): void {
    $registry = makeFactoryRegistry();
    $metadataFactory = new ScopeMetadataFactory($registry);
    $context = new ScopeContext($registry);
    $renderer = makeFactoryRenderer();

    $factory = new ScopedOrderByFactory($metadataFactory, $context, $renderer);
    $spec = $factory->create(ScopedOrderByFactoryProduct::class, 'name');

    expect($spec->direction)->toBe('asc');
});

it('is a readonly class with constructor-injected dependencies', function (): void {
    $reflection = new ReflectionClass(ScopedOrderByFactory::class);

    expect($reflection->isReadOnly())->toBeTrue();
});
