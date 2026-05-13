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
use Marko\Scope\Storage\ScopedOverridesEntity;
use Marko\Scope\Validation\ScopedEntityValidator;

// ─── Fixtures ────────────────────────────────────────────────────────────────

#[Table(name: 'plain_products')]
class ValidatorPlainProduct extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column]
    public string $name = '';
}

#[Table(name: 'scoped_products')]
class ValidatorScopedProduct extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Scoped(axes: ['store'])]
    #[Column]
    public string $name = '';

    #[Scoped(axes: ['store', 'website'])]
    #[Column]
    public string $description = '';
}

#[Table(extends: ValidatorScopedProduct::class)]
class ValidatorScopedProductOverrides extends ScopedOverridesEntity {}

#[Table(name: 'other_products')]
class ValidatorOtherProduct extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Scoped(axes: ['store'])]
    #[Column]
    public string $title = '';
}

#[Table(extends: ValidatorOtherProduct::class)]
class ValidatorOtherProductOverridesWrongBase extends Entity
{
    #[Column]
    public string $extra = '';
}

#[Table(name: 'trait_scoped_products')]
class ValidatorTraitScopedProduct extends Entity implements HasScopesInterface
{
    use HasScopes;

    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Scoped(axes: ['store'])]
    #[Column]
    public string $name = '';
}

#[Table(extends: ValidatorTraitScopedProduct::class)]
class ValidatorTraitScopedProductOverrides extends ScopedOverridesEntity {}

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

it('passes for an entity with no Scoped properties', function (): void {
    $scopeFactory = new ScopeMetadataFactory(makeValidatorRegistry());
    $entityFactory = new EntityMetadataFactory();
    $validator = new ScopedEntityValidator($scopeFactory, $entityFactory);

    expect(fn () => $validator->validate(ValidatorPlainProduct::class))->not->toThrow(Throwable::class);
});

it(
    'passes for an entity with Scoped properties when a matching ScopedOverridesEntity extender is registered',
    function (): void {
        $scopeFactory = new ScopeMetadataFactory(makeValidatorRegistry());
        $entityFactory = new EntityMetadataFactory();
        $entityFactory->linkExtenders(ValidatorScopedProduct::class, [ValidatorScopedProductOverrides::class]);
        $validator = new ScopedEntityValidator($scopeFactory, $entityFactory);

        expect(fn () => $validator->validate(ValidatorScopedProduct::class))->not->toThrow(Throwable::class);
    },
);

it(
    'throws ScopeConfigurationException when an entity has Scoped properties but no extender is linked',
    function (): void {
        $scopeFactory = new ScopeMetadataFactory(makeValidatorRegistry());
        $entityFactory = new EntityMetadataFactory();
        $validator = new ScopedEntityValidator($scopeFactory, $entityFactory);

        expect(fn () => $validator->validate(ValidatorScopedProduct::class))
            ->toThrow(ScopeConfigurationException::class);
    },
);

it(
    'throws ScopeConfigurationException when the linked extender does not extend ScopedOverridesEntity',
    function (): void {
        $scopeFactory = new ScopeMetadataFactory(makeValidatorRegistry());
        $entityFactory = new EntityMetadataFactory();
        $entityFactory->linkExtenders(ValidatorOtherProduct::class, [ValidatorOtherProductOverridesWrongBase::class]);
        $validator = new ScopedEntityValidator($scopeFactory, $entityFactory);

        expect(fn () => $validator->validate(ValidatorOtherProduct::class))
            ->toThrow(ScopeConfigurationException::class);
    },
);

it('includes the parent entity FQCN in the exception message', function (): void {
    $scopeFactory = new ScopeMetadataFactory(makeValidatorRegistry());
    $entityFactory = new EntityMetadataFactory();
    $validator = new ScopedEntityValidator($scopeFactory, $entityFactory);

    try {
        $validator->validate(ValidatorScopedProduct::class);
        expect(false)->toBeTrue('Expected exception was not thrown');
    } catch (ScopeConfigurationException $e) {
        expect($e->getMessage())->toContain(ValidatorScopedProduct::class);
    }
});

it('lists each Scoped property and its declared axes in the exception context', function (): void {
    $scopeFactory = new ScopeMetadataFactory(makeValidatorRegistry());
    $entityFactory = new EntityMetadataFactory();
    $validator = new ScopedEntityValidator($scopeFactory, $entityFactory);

    try {
        $validator->validate(ValidatorScopedProduct::class);
        expect(false)->toBeTrue('Expected exception was not thrown');
    } catch (ScopeConfigurationException $e) {
        expect($e->getContext())->toContain('name')
            ->and($e->getContext())->toContain('store')
            ->and($e->getContext())->toContain('description')
            ->and($e->getContext())->toContain('website');
    }
});

it(
    'provides a one-line class declaration including the Table extends attribute in the exception suggestion',
    function (): void {
        $scopeFactory = new ScopeMetadataFactory(makeValidatorRegistry());
        $entityFactory = new EntityMetadataFactory();
        $validator = new ScopedEntityValidator($scopeFactory, $entityFactory);

        try {
            $validator->validate(ValidatorScopedProduct::class);
            expect(false)->toBeTrue('Expected exception was not thrown');
        } catch (ScopeConfigurationException $e) {
            expect($e->getSuggestion())->toContain('#[Table(extends:')
                ->and($e->getSuggestion())->toContain(ValidatorScopedProduct::class . '::class')
                ->and($e->getSuggestion())->toContain('ScopedOverridesEntity');
        }
    },
);

it(
    'the traitAndCompanionConflict exception message names both the parent class and the conflicting extender class',
    function (): void {
        $scopeFactory = new ScopeMetadataFactory(makeValidatorRegistry());
        $entityFactory = new EntityMetadataFactory();
        $entityFactory->linkExtenders(
            ValidatorTraitScopedProduct::class,
            [ValidatorTraitScopedProductOverrides::class],
        );
        $validator = new ScopedEntityValidator($scopeFactory, $entityFactory);

        try {
            $validator->validate(ValidatorTraitScopedProduct::class);
            expect(false)->toBeTrue('Expected exception was not thrown');
        } catch (ScopeConfigurationException $e) {
            expect($e->getMessage())->toContain(ValidatorTraitScopedProduct::class)
                ->and($e->getMessage())->toContain(ValidatorTraitScopedProductOverrides::class);
        }
    },
);

it(
    'throws ScopeConfigurationException via traitAndCompanionConflict when entity uses HasScopes trait AND has a ScopedOverridesEntity extender registered',
    function (): void {
        $scopeFactory = new ScopeMetadataFactory(makeValidatorRegistry());
        $entityFactory = new EntityMetadataFactory();
        $entityFactory->linkExtenders(
            ValidatorTraitScopedProduct::class,
            [ValidatorTraitScopedProductOverrides::class],
        );
        $validator = new ScopedEntityValidator($scopeFactory, $entityFactory);

        expect(fn () => $validator->validate(ValidatorTraitScopedProduct::class))
            ->toThrow(ScopeConfigurationException::class);
    },
);

it(
    'throws ScopeConfigurationException with wrongOverridesExtenderBase when extender exists but is not ScopedOverridesEntity',
    function (): void {
        $scopeFactory = new ScopeMetadataFactory(makeValidatorRegistry());
        $entityFactory = new EntityMetadataFactory();
        $entityFactory->linkExtenders(ValidatorOtherProduct::class, [ValidatorOtherProductOverridesWrongBase::class]);
        $validator = new ScopedEntityValidator($scopeFactory, $entityFactory);

        expect(fn () => $validator->validate(ValidatorOtherProduct::class))
            ->toThrow(ScopeConfigurationException::class);
    },
);

it(
    'throws ScopeConfigurationException when entity has scoped properties but neither trait nor companion',
    function (): void {
        $scopeFactory = new ScopeMetadataFactory(makeValidatorRegistry());
        $entityFactory = new EntityMetadataFactory();
        $validator = new ScopedEntityValidator($scopeFactory, $entityFactory);

        expect(fn () => $validator->validate(ValidatorScopedProduct::class))
            ->toThrow(ScopeConfigurationException::class);
    },
);

it('passes validation when the entity has no scoped properties at all', function (): void {
    $scopeFactory = new ScopeMetadataFactory(makeValidatorRegistry());
    $entityFactory = new EntityMetadataFactory();
    $validator = new ScopedEntityValidator($scopeFactory, $entityFactory);

    $threw = false;
    try {
        $validator->validate(ValidatorPlainProduct::class);
    } catch (Throwable) {
        $threw = true;
    }

    expect($threw)->toBeFalse();
});

it('passes validation when the entity has a ScopedOverridesEntity companion (backward compat)', function (): void {
    $scopeFactory = new ScopeMetadataFactory(makeValidatorRegistry());
    $entityFactory = new EntityMetadataFactory();
    $entityFactory->linkExtenders(ValidatorScopedProduct::class, [ValidatorScopedProductOverrides::class]);
    $validator = new ScopedEntityValidator($scopeFactory, $entityFactory);

    $threw = false;
    try {
        $validator->validate(ValidatorScopedProduct::class);
    } catch (Throwable) {
        $threw = true;
    }

    expect($threw)->toBeFalse();
});

it(
    'passes validation when the entity class implements HasScopesInterface and has scoped properties',
    function (): void {
        $scopeFactory = new ScopeMetadataFactory(makeValidatorRegistry());
        $entityFactory = new EntityMetadataFactory();
        $validator = new ScopedEntityValidator($scopeFactory, $entityFactory);

        $threw = false;
        try {
            $validator->validate(ValidatorTraitScopedProduct::class);
        } catch (Throwable) {
            $threw = true;
        }

        expect($threw)->toBeFalse();
    },
);
