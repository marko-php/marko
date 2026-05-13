<?php

declare(strict_types=1);

use Marko\Scope\Axis\ScopeAxis;
use Marko\Scope\Context\ScopeContext;
use Marko\Scope\Exceptions\ScopeContextException;
use Marko\Scope\Exceptions\UnknownAxisException;
use Marko\Scope\Hierarchy\ScopeHierarchy;
use Marko\Scope\Registry\ScopeRegistryInterface;

function makeScopeRegistry(array $axes = []): ScopeRegistryInterface
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

it('accepts a current scope for an axis via in and is fluent', function (): void {
    $registry = makeScopeRegistry(['geo' => ['eu', 'eu.de', 'us']]);
    $context = new ScopeContext($registry);

    $result = $context->in('geo', 'eu.de');

    expect($result)->toBe($context);
});

it('returns the current scope path for a set axis via get', function (): void {
    $registry = makeScopeRegistry(['geo' => ['eu', 'eu.de', 'us']]);
    $context = new ScopeContext($registry);
    $context->in('geo', 'eu.de');

    expect($context->get('geo'))->toBe('eu.de');
});

it('returns null from get for an unset axis', function (): void {
    $registry = makeScopeRegistry(['geo' => ['eu', 'eu.de', 'us']]);
    $context = new ScopeContext($registry);

    expect($context->get('geo'))->toBeNull();
});

it('throws UnknownAxisException when in is called with an unknown axis', function (): void {
    $registry = makeScopeRegistry(['geo' => ['eu', 'eu.de', 'us']]);
    $context = new ScopeContext($registry);

    expect(fn () => $context->in('locale', 'en'))->toThrow(UnknownAxisException::class);
});

it('throws ScopeContextException when in is called with a path not in the axis hierarchy', function (): void {
    $registry = makeScopeRegistry(['geo' => ['eu', 'eu.de', 'us']]);
    $context = new ScopeContext($registry);

    expect(fn () => $context->in('geo', 'eu.fr'))->toThrow(ScopeContextException::class);
});

it('clears a single axis via clear and all axes via clearAll', function (): void {
    $registry = makeScopeRegistry(['geo' => ['eu', 'eu.de', 'us'], 'locale' => ['en', 'fr']]);
    $context = new ScopeContext($registry);
    $context->in('geo', 'eu.de')->in('locale', 'en');

    $context->clear('geo');

    expect($context->get('geo'))->toBeNull()
        ->and($context->get('locale'))->toBe('en');

    $context->clearAll();

    expect($context->get('locale'))->toBeNull();
});

it('lists all axes currently set via activeAxes', function (): void {
    $registry = makeScopeRegistry(['geo' => ['eu', 'eu.de', 'us'], 'locale' => ['en', 'fr'], 'channel' => ['web']]);
    $context = new ScopeContext($registry);

    expect($context->activeAxes())->toBe([]);

    $context->in('geo', 'eu')->in('locale', 'en');

    expect($context->activeAxes())->toBe(['geo', 'locale']);

    $context->clear('geo');

    expect($context->activeAxes())->toBe(['locale']);
});
