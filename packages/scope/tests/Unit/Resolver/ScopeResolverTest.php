<?php

declare(strict_types=1);

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Marko\Scope\Attributes\Scoped;
use Marko\Scope\Axis\ScopeAxis;
use Marko\Scope\Context\ScopeContext;
use Marko\Scope\Exceptions\ScopeContextException;
use Marko\Scope\Hierarchy\ScopeHierarchy;
use Marko\Scope\Metadata\ScopeMetadataFactory;
use Marko\Scope\Registry\ScopeRegistryInterface;
use Marko\Scope\Resolution\ScopeWalker;
use Marko\Scope\Resolver\ScopeResolver;
use Marko\Scope\Scope;
use Marko\Scope\Storage\HasScopes;
use Marko\Scope\Storage\HasScopesInterface;

// ─── Fixtures ────────────────────────────────────────────────────────────────

#[Table(name: 'resolver_products')]
class ResolverProduct extends Entity
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Scoped(axes: ['store'])]
    #[Column]
    public string $name = 'default-name';

    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column]
    public string $sku = 'default-sku';
}

#[Table(name: 'trait_resolver_products')]
class TraitResolverProduct extends Entity implements HasScopesInterface
{
    use HasScopes;

    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Scoped(axes: ['store'])]
    #[Column]
    public string $name = 'default-name';

    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column]
    public string $sku = 'default-sku';
}

#[Table(extends: ResolverProduct::class)]
class ManualCompanionProduct extends Entity implements HasScopesInterface
{
    use HasScopes;
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

function makeResolverRegistry(array $axes = ['store' => ['global', 'global.us']]): ScopeRegistryInterface
{
    return new class ($axes) implements ScopeRegistryInterface
    {
        /** @var array<string, ScopeAxis> */
        private array $builtAxes;

        public function __construct(array $axes)
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

function makeResolverSetup(): array
{
    $registry = makeResolverRegistry();
    $context = new ScopeContext($registry);
    $scopeMetaFactory = new ScopeMetadataFactory($registry);
    $walker = new ScopeWalker();

    return [$registry, $context, $scopeMetaFactory, $walker];
}

function makeResolver(): array
{
    [$registry, $context, $scopeMetaFactory, $walker] = makeResolverSetup();
    $resolver = new ScopeResolver($scopeMetaFactory, $walker, $context);

    return [$resolver, $context, $registry];
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('resolves a property value via current ScopeContext returning the walker match', function (): void {
    [$resolver, $context] = makeResolver();
    $context->in('store', 'global.us');

    $product = new ResolverProduct();
    $companion = new ManualCompanionProduct();
    $companion->setOverride('store:global.us', 'name', 'US Name');
    $product->attachCompanion($companion);

    $result = $resolver->resolved($product, 'name');

    expect($result)->toBe('US Name');
});

it('resolves at an explicit scope via resolvedAt without consulting ScopeContext', function (): void {
    [$resolver, $context] = makeResolver();
    // context set to global.us, but we resolve at global explicitly
    $context->in('store', 'global.us');

    $product = new ResolverProduct();
    $companion = new ManualCompanionProduct();
    $companion->setOverride('store:global', 'name', 'Global Name');
    $companion->setOverride('store:global.us', 'name', 'US Name');
    $product->attachCompanion($companion);

    $scope = new Scope('store', 'global');
    $result = $resolver->resolvedAt($product, 'name', $scope);

    expect($result)->toBe('Global Name');
});

it('clears an override via clearOverride leaving the companion otherwise intact', function (): void {
    [$resolver] = makeResolver();

    $product = new ResolverProduct();
    $companion = new ManualCompanionProduct();
    $companion->setOverride('store:global.us', 'name', 'US Name');
    $companion->setOverride('store:global.us', 'sku', 'SKU-US');
    $product->attachCompanion($companion);

    $scope = new Scope('store', 'global.us');
    $resolver->clearOverride($product, 'name', $scope);

    expect($companion->hasOverride('store:global.us', 'name'))->toBeFalse()
        ->and($companion->override('store:global.us', 'sku'))->toBe('SKU-US');
});

it('throws ScopeContextException when setOverride targets a property without Scoped', function (): void {
    [$resolver] = makeResolver();

    $product = new ResolverProduct();
    // 'sku' is not marked with #[Scoped]
    $scope = new Scope('store', 'global.us');

    expect(fn () => $resolver->setOverride($product, 'sku', 'SKU-123', $scope))
        ->toThrow(ScopeContextException::class);
});

it('throws ScopeContextException when resolving an unknown property', function (): void {
    [$resolver, $context] = makeResolver();
    $context->in('store', 'global.us');

    $product = new ResolverProduct();

    expect(fn () => $resolver->resolved($product, 'nonExistentProperty'))
        ->toThrow(ScopeContextException::class);
});

it('falls back to the entity\'s column property value when no override is found', function (): void {
    [$resolver, $context] = makeResolver();
    $context->in('store', 'global.us');

    $product = new ResolverProduct();
    $product->name = 'base-name';
    $companion = new ManualCompanionProduct();
    // No override set for 'name'
    $product->attachCompanion($companion);

    $result = $resolver->resolved($product, 'name');

    expect($result)->toBe('base-name');
});

it('resolves a scoped value when the entity itself implements HasScopesInterface', function (): void {
    [$resolver, $context] = makeResolver();
    $context->in('store', 'global.us');

    $product = new TraitResolverProduct();
    $product->setOverride('store:global.us', 'name', 'Trait US Name');

    $result = $resolver->resolved($product, 'name');

    expect($result)->toBe('Trait US Name');
});

it('falls back to the column value when entity implements HasScopesInterface but has no override', function (): void {
    [$resolver, $context] = makeResolver();
    $context->in('store', 'global.us');

    $product = new TraitResolverProduct();
    $product->name = 'base-trait-name';

    $result = $resolver->resolved($product, 'name');

    expect($result)->toBe('base-trait-name');
});

it(
    'sets an override directly on the entity when it implements HasScopesInterface and no companion is attached or created',
    function (): void {
        [$resolver] = makeResolver();

        $product = new TraitResolverProduct();

        $scope = new Scope('store', 'global.us');
        $resolver->setOverride($product, 'name', 'Direct Override', $scope);

        expect($product->override('store:global.us', 'name'))->toBe('Direct Override')
            ->and($product->companions())->toBeEmpty();
    },
);

it('clears an override directly on the entity when it implements HasScopesInterface', function (): void {
    [$resolver] = makeResolver();

    $product = new TraitResolverProduct();
    $product->setOverride('store:global.us', 'name', 'To Be Cleared');

    $scope = new Scope('store', 'global.us');
    $resolver->clearOverride($product, 'name', $scope);

    expect($product->hasOverride('store:global.us', 'name'))->toBeFalse();
});

it('silently no-ops when clearOverride is called on a trait-based entity that has no overrides yet', function (): void {
    [$resolver] = makeResolver();

    $product = new TraitResolverProduct();

    $scope = new Scope('store', 'global.us');

    // Should not throw
    $resolver->clearOverride($product, 'name', $scope);

    expect($product->scopes)->toBeNull();
});

it(
    'throws ScopeContextException when setOverride is called on an entity that does not implement HasScopesInterface and has no companion registered as an extender',
    function (): void {
        // Build a resolver with NO extenders registered for ResolverProduct
        $registry = makeResolverRegistry();
        $context = new ScopeContext($registry);
        $scopeMetaFactory = new ScopeMetadataFactory($registry);
        $walker = new ScopeWalker();
        $resolver = new ScopeResolver($scopeMetaFactory, $walker, $context);

        $product = new ResolverProduct();
        $scope = new Scope('store', 'global.us');

        expect(fn () => $resolver->setOverride($product, 'name', 'Name', $scope))
            ->toThrow(ScopeContextException::class);
    },
);

it('resolvedAt returns the correct value for an explicit scope on a trait-based entity', function (): void {
    [$resolver, $context] = makeResolver();
    $context->in('store', 'global.us');

    $product = new TraitResolverProduct();
    $product->setOverride('store:global', 'name', 'Global Name');
    $product->setOverride('store:global.us', 'name', 'US Name');

    $scope = new Scope('store', 'global');
    $result = $resolver->resolvedAt($product, 'name', $scope);

    expect($result)->toBe('Global Name');
});

it(
    'returns the column value when resolvedAt finds no override for the given scope on a trait-based entity',
    function (): void {
        [$resolver] = makeResolver();

        $product = new TraitResolverProduct();
        $product->name = 'column-value';

        $scope = new Scope('store', 'global.us');
        $result = $resolver->resolvedAt($product, 'name', $scope);

        expect($result)->toBe('column-value');
    },
);

it(
    'prefers the entity itself over any attached companion when both implement HasScopesInterface (entity-self wins ordering)',
    function (): void {
        [$resolver, $context] = makeResolver();
        $context->in('store', 'global.us');

        $product = new TraitResolverProduct();
        $product->setOverride('store:global.us', 'name', 'Entity Override');

        // Attach a companion that also has an override — entity should win
        $companion = new ManualCompanionProduct();
        $companion->setOverride('store:global.us', 'name', 'Companion Override');
        $product->attachCompanion($companion);

        $result = $resolver->resolved($product, 'name');

        expect($result)->toBe('Entity Override');
    },
);

it('does not create or attach a companion when setOverride is called on a trait-based entity', function (): void {
    [$resolver] = makeResolver();

    $product = new TraitResolverProduct();

    $scope = new Scope('store', 'global.us');
    $resolver->setOverride($product, 'name', 'Direct', $scope);

    expect($product->companions())->toBeEmpty();
});

it(
    'ScopeResolver setOverride throws ScopeContextException when entity has no HasScopesInterface and no compatible companion',
    function (): void {
        $registry = makeResolverRegistry();
        $context = new ScopeContext($registry);
        $scopeMetaFactory = new ScopeMetadataFactory($registry);
        $walker = new ScopeWalker();
        $resolver = new ScopeResolver($scopeMetaFactory, $walker, $context);

        $product = new ResolverProduct();
        $scope = new Scope('store', 'global.us');

        expect(fn () => $resolver->setOverride($product, 'name', 'Name', $scope))
            ->toThrow(ScopeContextException::class);
    },
);

it('ScopeResolver setOverride works on a trait-based entity', function (): void {
    $registry = makeResolverRegistry();
    $context = new ScopeContext($registry);
    $scopeMetaFactory = new ScopeMetadataFactory($registry);
    $walker = new ScopeWalker();
    $resolver = new ScopeResolver($scopeMetaFactory, $walker, $context);

    $product = new TraitResolverProduct();
    $scope = new Scope('store', 'global.us');
    $resolver->setOverride($product, 'name', 'Trait Override', $scope);

    expect($product->override('store:global.us', 'name'))->toBe('Trait Override')
        ->and($product->companions())->toBeEmpty();
});

it('ScopeResolver setOverride works when a manual HasScopesInterface companion is attached', function (): void {
    $registry = makeResolverRegistry();
    $context = new ScopeContext($registry);
    $scopeMetaFactory = new ScopeMetadataFactory($registry);
    $walker = new ScopeWalker();
    $resolver = new ScopeResolver($scopeMetaFactory, $walker, $context);

    $product = new ResolverProduct();
    $companion = new ManualCompanionProduct();
    $product->attachCompanion($companion);
    $scope = new Scope('store', 'global.us');
    $resolver->setOverride($product, 'name', 'Companion Override', $scope);

    expect($companion->override('store:global.us', 'name'))->toBe('Companion Override');
});

it('ScopeResolver resolved works with a manual HasScopesInterface companion (not ScopedOverridesEntity)', function (): void {
    $registry = makeResolverRegistry();
    $context = new ScopeContext($registry);
    $context->in('store', 'global.us');
    $scopeMetaFactory = new ScopeMetadataFactory($registry);
    $walker = new ScopeWalker();
    $resolver = new ScopeResolver($scopeMetaFactory, $walker, $context);

    $product = new ResolverProduct();
    $companion = new ManualCompanionProduct();
    $companion->setOverride('store:global.us', 'name', 'Manual Companion Name');
    $product->attachCompanion($companion);

    $result = $resolver->resolved($product, 'name');

    expect($result)->toBe('Manual Companion Name');
});

it('ScopeResolver does not have a createCompanion method', function (): void {
    $reflection = new ReflectionClass(ScopeResolver::class);

    expect($reflection->hasMethod('createCompanion'))->toBeFalse();
});
