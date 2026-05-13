<?php

declare(strict_types=1);

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Scope\Attributes\Scoped;
use Marko\Scope\Axis\ScopeAxis;
use Marko\Scope\Exceptions\ScopeConfigurationException;
use Marko\Scope\Hierarchy\ScopeHierarchy;
use Marko\Scope\Metadata\ScopeMetadataFactory;
use Marko\Scope\Registry\ScopeRegistryInterface;
use Marko\Scope\Storage\HasScopes;
use Marko\Scope\Storage\HasScopesInterface;
use Marko\Scope\Validation\ScopedEntityValidator;

// ─── Fixtures ────────────────────────────────────────────────────────────────

#[Table(name: 'plain_products')]
class ValidatorPlainProduct extends Entity
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column]
    public string $name = '';
}

#[Table(name: 'scoped_products')]
class ValidatorScopedProduct extends Entity
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Scoped(axes: ['store'])]
    #[Column]
    public string $name = '';

    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Scoped(axes: ['store', 'website'])]
    #[Column]
    public string $description = '';
}

#[Table(name: 'trait_scoped_products')]
class ValidatorTraitScopedProduct extends Entity implements HasScopesInterface
{
    use HasScopes;

    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Scoped(axes: ['store'])]
    #[Column]
    public string $name = '';
}

#[Table(name: 'companion_scoped_products')]
class ValidatorCompanionScopedProduct extends Entity
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Scoped(axes: ['store'])]
    #[Column]
    public string $name = '';
}

#[Table(extends: ValidatorCompanionScopedProduct::class)]
class ValidatorCompanionScopedProductOverrides extends Entity implements HasScopesInterface
{
    use HasScopes;
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

function makeValidatorRegistry(): ScopeRegistryInterface
{
    return new class () implements ScopeRegistryInterface
    {
        public function hasAxis(string $name): bool
        {
            return in_array($name, ['store', 'website'], true);
        }

        public function getAxis(string $name): ScopeAxis
        {
            throw new RuntimeException('Not implemented');
        }

        public function listAxes(): array
        {
            return ['store', 'website'];
        }

        public function getHierarchy(string $axisName): ScopeHierarchy
        {
            throw new RuntimeException('Not implemented');
        }
    };
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('accepts a trait-based entity as valid scopes storage (HasScopesInterface on entity)', function (): void {
    $scopeFactory = new ScopeMetadataFactory(makeValidatorRegistry());
    $entityFactory = new EntityMetadataFactory();
    $validator = new ScopedEntityValidator($scopeFactory, $entityFactory);

    expect(fn () => $validator->validate(ValidatorTraitScopedProduct::class))->not->toThrow(Throwable::class);
});

it('accepts a companion that implements HasScopesInterface as valid scopes storage', function (): void {
    $scopeFactory = new ScopeMetadataFactory(makeValidatorRegistry());
    $entityFactory = new EntityMetadataFactory();
    $entityFactory->linkExtenders(
        ValidatorCompanionScopedProduct::class,
        [ValidatorCompanionScopedProductOverrides::class],
    );
    $validator = new ScopedEntityValidator($scopeFactory, $entityFactory);

    expect(fn () => $validator->validate(ValidatorCompanionScopedProduct::class))->not->toThrow(Throwable::class);
});

it('accepts an entity with no scoped properties regardless of storage', function (): void {
    $scopeFactory = new ScopeMetadataFactory(makeValidatorRegistry());
    $entityFactory = new EntityMetadataFactory();
    $validator = new ScopedEntityValidator($scopeFactory, $entityFactory);

    expect(fn () => $validator->validate(ValidatorPlainProduct::class))->not->toThrow(Throwable::class);
});

it(
    'throws ScopeConfigurationException missingScopesStorage when entity has scoped properties but no storage',
    function (): void {
        $scopeFactory = new ScopeMetadataFactory(makeValidatorRegistry());
        $entityFactory = new EntityMetadataFactory();
        $validator = new ScopedEntityValidator($scopeFactory, $entityFactory);

        expect(fn () => $validator->validate(ValidatorScopedProduct::class))
            ->toThrow(ScopeConfigurationException::class);
    },
);

it('the missingScopesStorage exception message names the entity class and describes both remedies', function (): void {
    $scopeFactory = new ScopeMetadataFactory(makeValidatorRegistry());
    $entityFactory = new EntityMetadataFactory();
    $validator = new ScopedEntityValidator($scopeFactory, $entityFactory);

    try {
        $validator->validate(ValidatorScopedProduct::class);
        expect(false)->toBeTrue('Expected ScopeConfigurationException was not thrown');
    } catch (ScopeConfigurationException $e) {
        expect($e->getMessage())
            ->toContain(ValidatorScopedProduct::class)
            ->and($e->getMessage())->toContain('HasScopes')
            ->and($e->getMessage())->toContain('HasScopesInterface')
            ->and($e->getMessage())->toContain('companion');
    }
});
