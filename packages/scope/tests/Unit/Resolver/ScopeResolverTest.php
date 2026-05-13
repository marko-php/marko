<?php

declare(strict_types=1);

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityMetadataFactory;
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
use Marko\Scope\Storage\ScopedOverridesEntity;

// ─── Fixtures ────────────────────────────────────────────────────────────────

#[Table(name: 'resolver_products')]
class ResolverProduct extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Scoped(axes: ['store'])]
    #[Column]
    public string $name = 'default-name';

    #[Column]
    public string $sku = 'default-sku';
}

#[Table(extends: ResolverProduct::class)]
class ResolverProductOverrides extends ScopedOverridesEntity {}

#[Table(name: 'trait_resolver_products')]
class TraitResolverProduct extends Entity implements HasScopesInterface
{
    use HasScopes;

    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Scoped(axes: ['store'])]
    #[Column]
    public string $name = 'default-name';

    #[Column]
    public string $sku = 'default-sku';
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

function makeResolverRegistry(array $axes = ['store' => ['global', 'global.us']]): ScopeRegistryInterface
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

function makeResolverSetup(): array
{
    $registry = makeResolverRegistry();
    $context = new ScopeContext($registry);
    $scopeMetaFactory = new ScopeMetadataFactory($registry);
    $walker = new ScopeWalker();
    $entityMetaFactory = new EntityMetadataFactory();
    $entityMetaFactory->linkExtenders(ResolverProduct::class, [ResolverProductOverrides::class]);

    return [$registry, $context, $scopeMetaFactory, $walker, $entityMetaFactory];
}

function makeResolver(): array
{
    [$registry, $context, $scopeMetaFactory, $walker, $entityMetaFactory] = makeResolverSetup();
    $resolver = new ScopeResolver($scopeMetaFactory, $walker, $context, $entityMetaFactory);

    return [$resolver, $context, $registry];
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('resolves a property value via current ScopeContext returning the walker match', function (): void {
    [$resolver, $context] = makeResolver();
    $context->in('store', 'global.us');

    $product = new ResolverProduct();
    $overrides = new ResolverProductOverrides();
    $overrides->setOverride('store:global.us', 'name', 'US Name');
    $product->attachCompanion($overrides);

    $result = $resolver->resolved($product, 'name');

    expect($result)->toBe('US Name');
});

it('resolves at an explicit scope via resolvedAt without consulting ScopeContext', function (): void {
    [$resolver, $context] = makeResolver();
    // context set to global.us, but we resolve at global explicitly
    $context->in('store', 'global.us');

    $product = new ResolverProduct();
    $overrides = new ResolverProductOverrides();
    $overrides->setOverride('store:global', 'name', 'Global Name');
    $overrides->setOverride('store:global.us', 'name', 'US Name');
    $product->attachCompanion($overrides);

    $scope = new Scope('store', 'global');
    $result = $resolver->resolvedAt($product, 'name', $scope);

    expect($result)->toBe('Global Name');
});

it('sets an override via setOverride attaching a ScopedOverridesEntity companion if missing', function (): void {
    [$resolver, $context] = makeResolver();
    $context->in('store', 'global.us');

    $product = new ResolverProduct();
    // No companion attached yet

    $scope = new Scope('store', 'global.us');
    $resolver->setOverride($product, 'name', 'US Name', $scope);

    $companion = $product->companion(ResolverProductOverrides::class);

    expect($companion)->toBeInstanceOf(ResolverProductOverrides::class)
        ->and($companion->getOverride('store:global.us', 'name'))->toBe('US Name');
});

it('sets an override on a new (unsaved) entity then saves so both rows reflect the override', function (): void {
    [$resolver] = makeResolver();

    $product = new ResolverProduct();
    // Simulate "new (unsaved)" entity — no companion exists yet

    $scope = new Scope('store', 'global.us');
    $resolver->setOverride($product, 'name', 'Fresh Name', $scope);

    $companion = $product->companion(ResolverProductOverrides::class);

    expect($companion)->toBeInstanceOf(ResolverProductOverrides::class)
        ->and($companion->getOverride('store:global.us', 'name'))->toBe('Fresh Name');
});

it('clears an override via clearOverride leaving the companion otherwise intact', function (): void {
    [$resolver] = makeResolver();

    $product = new ResolverProduct();
    $overrides = new ResolverProductOverrides();
    $overrides->setOverride('store:global.us', 'name', 'US Name');
    $overrides->setOverride('store:global.us', 'sku', 'SKU-US');
    $product->attachCompanion($overrides);

    $scope = new Scope('store', 'global.us');
    $resolver->clearOverride($product, 'name', $scope);

    $companion = $product->companion(ResolverProductOverrides::class);

    expect($companion->hasOverride('store:global.us', 'name'))->toBeFalse()
        ->and($companion->getOverride('store:global.us', 'sku'))->toBe('SKU-US');
});

it(
    'discovers the correct ScopedOverridesEntity subclass for a given parent entity class via EntityMetadata::extenders',
    function (): void {
        [$resolver] = makeResolver();

        $product = new ResolverProduct();
        // No companion exists; resolver must discover ResolverProductOverrides via extenders

        $scope = new Scope('store', 'global');
        $resolver->setOverride($product, 'name', 'Global Name', $scope);

        // The companion created should be the correct subclass
        $companion = $product->companion(ResolverProductOverrides::class);

        expect($companion)->toBeInstanceOf(ResolverProductOverrides::class)
            ->and($companion->getOverride('store:global', 'name'))->toBe('Global Name');
    },
);

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
    $overrides = new ResolverProductOverrides();
    // No override set for 'name'
    $product->attachCompanion($overrides);

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

it('resolves a scoped value when a ScopedOverridesEntity companion is attached (backward compat)', function (): void {
    [$resolver, $context] = makeResolver();
    $context->in('store', 'global.us');

    $product = new ResolverProduct();
    $overrides = new ResolverProductOverrides();
    $overrides->setOverride('store:global.us', 'name', 'Companion Name');
    $product->attachCompanion($overrides);

    $result = $resolver->resolved($product, 'name');

    expect($result)->toBe('Companion Name');
});

it(
    'sets an override directly on the entity when it implements HasScopesInterface and no companion is attached or created',
    function (): void {
        [$resolver] = makeResolver();

        $product = new TraitResolverProduct();

        $scope = new Scope('store', 'global.us');
        $resolver->setOverride($product, 'name', 'Direct Override', $scope);

        expect($product->getOverride('store:global.us', 'name'))->toBe('Direct Override')
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
        $entityMetaFactory = new EntityMetadataFactory();
        // No linkExtenders call — no companion available
        $resolver = new ScopeResolver($scopeMetaFactory, $walker, $context, $entityMetaFactory);

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
        $companion = new ResolverProductOverrides();
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
