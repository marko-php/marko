<?php

declare(strict_types=1);

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Marko\Scope\Axis\ScopeAxis;
use Marko\Scope\Context\ScopeContext;
use Marko\Scope\Exceptions\UnknownAxisException;
use Marko\Scope\Hierarchy\ScopeHierarchy;
use Marko\Scope\Registry\ScopeRegistryInterface;
use Marko\Scope\Resolution\ScopeWalker;
use Marko\Scope\Scope;
use Marko\Scope\Storage\HasScopes;
use Marko\Scope\Storage\HasScopesInterface;

// Standalone HasScopesInterface implementor for walker tests (no parent entity needed)
class WalkerTestOverrides implements HasScopesInterface
{
    use HasScopes;
}

// Trait-based entity fixture
#[Table(name: 'walker_trait_products')]
class WalkerTraitProduct extends Entity implements HasScopesInterface
{
    use HasScopes;

    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;
}

function makeWalkerRegistry(array $axes = []): ScopeRegistryInterface
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
            if (!isset($this->builtAxes[$name])) {
                throw UnknownAxisException::forAxis($name);
            }

            return $this->builtAxes[$name];
        }

        /** @return list<string> */
        public function listAxes(): array
        {
            return array_keys($this->builtAxes);
        }

        public function getHierarchy(string $axisName): ScopeHierarchy
        {
            return $this->getAxis($axisName)->hierarchy;
        }
    };
}

it('returns the override at the current scope when one exists', function (): void {
    $registry = makeWalkerRegistry(['geo' => ['eu', 'eu.de']]);
    $context = new ScopeContext($registry);
    $context->in('geo', 'eu.de');

    $overrides = new WalkerTestOverrides();
    $overrides->setOverride('geo:eu.de', 'name', 'Hemd');

    $walker = new ScopeWalker();
    $result = $walker->walk($overrides, 'name', ['geo'], $context, $registry);

    expect($result->isFound())->toBeTrue()
        ->and($result->value())->toBe('Hemd');
});

it('returns an ancestor override when no override exists at the current scope', function (): void {
    $registry = makeWalkerRegistry(['geo' => ['eu', 'eu.de']]);
    $context = new ScopeContext($registry);
    $context->in('geo', 'eu.de');

    $overrides = new WalkerTestOverrides();
    $overrides->setOverride('geo:eu', 'name', 'Shirt-EU');

    $walker = new ScopeWalker();
    $result = $walker->walk($overrides, 'name', ['geo'], $context, $registry);

    expect($result->isFound())->toBeTrue()
        ->and($result->value())->toBe('Shirt-EU');
});

it('returns notFound when no override exists at any walked scope', function (): void {
    $registry = makeWalkerRegistry(['geo' => ['eu', 'eu.de']]);
    $context = new ScopeContext($registry);
    $context->in('geo', 'eu.de');

    $overrides = new WalkerTestOverrides();

    $walker = new ScopeWalker();
    $result = $walker->walk($overrides, 'name', ['geo'], $context, $registry);

    expect($result->isFound())->toBeFalse();
});

it('walks axes in declared priority order returning the first axis match', function (): void {
    $registry = makeWalkerRegistry([
        'geo'    => ['eu', 'eu.de'],
        'locale' => ['de', 'de-DE'],
    ]);
    $context = new ScopeContext($registry);
    $context->in('geo', 'eu.de')->in('locale', 'de-DE');

    $overrides = new WalkerTestOverrides();
    $overrides->setOverride('geo:eu.de', 'name', 'Hemd');
    $overrides->setOverride('locale:de-DE', 'name', 'Hemd-de');

    $walker = new ScopeWalker();

    // geo first: should return geo value
    $result = $walker->walk($overrides, 'name', ['geo', 'locale'], $context, $registry);
    expect($result->isFound())->toBeTrue()
        ->and($result->value())->toBe('Hemd');

    // locale first: should return locale value
    $resultLocaleFirst = $walker->walk($overrides, 'name', ['locale', 'geo'], $context, $registry);
    expect($resultLocaleFirst->isFound())->toBeTrue()
        ->and($resultLocaleFirst->value())->toBe('Hemd-de');
});

it('skips axes that are not set in ScopeContext', function (): void {
    $registry = makeWalkerRegistry([
        'geo'    => ['eu', 'eu.de'],
        'locale' => ['de', 'de-DE'],
    ]);
    $context = new ScopeContext($registry);
    // Only geo is set in context; locale is not set
    $context->in('geo', 'eu.de');

    $overrides = new WalkerTestOverrides();
    $overrides->setOverride('locale:de', 'name', 'Hallo');
    $overrides->setOverride('geo:eu.de', 'name', 'Hemd');

    $walker = new ScopeWalker();
    // locale declared first but not in context — must skip and return geo match
    $result = $walker->walk($overrides, 'name', ['locale', 'geo'], $context, $registry);

    expect($result->isFound())->toBeTrue()
        ->and($result->value())->toBe('Hemd');
});

it('preserves an explicit null override and does not fall through it within an axis', function (): void {
    $registry = makeWalkerRegistry(['geo' => ['eu', 'eu.de']]);
    $context = new ScopeContext($registry);
    $context->in('geo', 'eu.de');

    $overrides = new WalkerTestOverrides();
    $overrides->setOverride('geo:eu', 'name', 'Shirt-EU');
    $overrides->setOverride('geo:eu.de', 'name', null); // explicit null at current scope

    $walker = new ScopeWalker();
    $result = $walker->walk($overrides, 'name', ['geo'], $context, $registry);

    // Should return found(null) — not fall through to the eu ancestor
    expect($result->isFound())->toBeTrue()
        ->and($result->value())->toBeNull();
});

it(
    'falls through an axis with no overrides for the property to the next axis (cross-axis fallthrough)',
    function (): void {
        $registry = makeWalkerRegistry([
            'geo'    => ['eu', 'eu.de'],
            'locale' => ['de', 'de.formal'],
        ]);
        $context = new ScopeContext($registry);
        $context->in('geo', 'eu.de')->in('locale', 'de.formal');

        $overrides = new WalkerTestOverrides();
        // No override for 'name' under geo; only under locale ancestor
        $overrides->setOverride('locale:de', 'name', 'Hallo');

        $walker = new ScopeWalker();
        // geo first — no match for 'name' anywhere in geo; should fall through to locale
        $result = $walker->walk($overrides, 'name', ['geo', 'locale'], $context, $registry);

        expect($result->isFound())->toBeTrue()
            ->and($result->value())->toBe('Hallo');
    },
);

it(
    'stops cross-axis fallthrough when an explicit null is found in an axis (null counts as a found value)',
    function (): void {
        $registry = makeWalkerRegistry([
            'geo'    => ['eu', 'eu.de'],
            'locale' => ['de', 'de.formal'],
        ]);
        $context = new ScopeContext($registry);
        $context->in('geo', 'eu.de')->in('locale', 'de.formal');

        $overrides = new WalkerTestOverrides();
        // geo has an explicit null — should count as "found" and stop cross-axis fallthrough
        $overrides->setOverride('geo:eu.de', 'name', null);
        // locale has a real value — but should NOT be reached
        $overrides->setOverride('locale:de', 'name', 'Hallo');

        $walker = new ScopeWalker();
        $result = $walker->walk($overrides, 'name', ['geo', 'locale'], $context, $registry);

        expect($result->isFound())->toBeTrue()
            ->and($result->value())->toBeNull();
    },
);

it('returns notFound when no axes are declared and no overrides exist', function (): void {
    $registry = makeWalkerRegistry([]);
    $context = new ScopeContext($registry);

    $overrides = new WalkerTestOverrides();

    $walker = new ScopeWalker();
    $result = $walker->walk($overrides, 'name', [], $context, $registry);

    expect($result->isFound())->toBeFalse();
});

it('resolves an override via walkAt when passed a HasScopesInterface implementor', function (): void {
    $registry = makeWalkerRegistry(['geo' => ['eu', 'eu.de']]);
    $scope = new Scope(axisName: 'geo', path: 'eu.de');

    $overrides = new WalkerTraitProduct();
    $overrides->setOverride('geo:eu.de', 'name', 'Hemd');

    $walker = new ScopeWalker();
    $result = $walker->walkAt($overrides, 'name', ['geo'], $scope, $registry);

    expect($result->isFound())->toBeTrue()
        ->and($result->value())->toBe('Hemd');
});

it('walks hierarchy ancestors when the exact scope path has no override', function (): void {
    $registry = makeWalkerRegistry(['geo' => ['eu', 'eu.de']]);
    $scope = new Scope(axisName: 'geo', path: 'eu.de');

    $overrides = new WalkerTraitProduct();
    // Only set override on ancestor 'eu', not on 'eu.de'
    $overrides->setOverride('geo:eu', 'name', 'Shirt-EU');

    $walker = new ScopeWalker();
    $result = $walker->walkAt($overrides, 'name', ['geo'], $scope, $registry);

    expect($result->isFound())->toBeTrue()
        ->and($result->value())->toBe('Shirt-EU');
});

it('returns notFound via walkAt when the axis does not match', function (): void {
    $registry = makeWalkerRegistry(['geo' => ['eu', 'eu.de']]);
    $scope = new Scope(axisName: 'locale', path: 'de');

    $overrides = new WalkerTraitProduct();
    $overrides->setOverride('geo:eu.de', 'name', 'Hemd');

    $walker = new ScopeWalker();
    $result = $walker->walkAt($overrides, 'name', ['geo'], $scope, $registry);

    expect($result->isFound())->toBeFalse();
});

it('returns notFound via walk when the HasScopesInterface implementor has no matching override', function (): void {
    $registry = makeWalkerRegistry(['geo' => ['eu', 'eu.de']]);
    $context = new ScopeContext($registry);
    $context->in('geo', 'eu.de');

    $overrides = new WalkerTraitProduct();

    $walker = new ScopeWalker();
    $result = $walker->walk($overrides, 'name', ['geo'], $context, $registry);

    expect($result->isFound())->toBeFalse();
});

it(
    'resolves an override via walk when passed a HasScopesInterface implementor that is not ScopedOverridesEntity',
    function (): void {
        $registry = makeWalkerRegistry(['geo' => ['eu', 'eu.de']]);
        $context = new ScopeContext($registry);
        $context->in('geo', 'eu.de');

        $overrides = new WalkerTraitProduct();
        $overrides->setOverride('geo:eu.de', 'name', 'Hemd');

        $walker = new ScopeWalker();
        $result = $walker->walk($overrides, 'name', ['geo'], $context, $registry);

        expect($result->isFound())->toBeTrue()
            ->and($result->value())->toBe('Hemd');
    },
);
