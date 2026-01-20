<?php

declare(strict_types=1);

use Marko\Core\Exceptions\CircularDependencyException;
use Marko\Core\Exceptions\ModuleException;
use Marko\Core\Module\DependencyResolver;
use Marko\Core\Module\ModuleManifest;

it('sorts modules with no dependencies in discovery order', function (): void {
    $moduleA = new ModuleManifest(
        name: 'vendor/module-a',
        version: '1.0.0',
    );
    $moduleB = new ModuleManifest(
        name: 'vendor/module-b',
        version: '1.0.0',
    );
    $moduleC = new ModuleManifest(
        name: 'vendor/module-c',
        version: '1.0.0',
    );

    $resolver = new DependencyResolver();
    $sorted = $resolver->resolve([$moduleA, $moduleB, $moduleC]);

    $names = array_map(fn (ModuleManifest $m) => $m->name, $sorted);

    expect($names)->toBe(['vendor/module-a', 'vendor/module-b', 'vendor/module-c']);
});

it('loads required modules before dependents', function (): void {
    // Module B requires Module A (via composer.json require)
    $moduleA = new ModuleManifest(
        name: 'vendor/module-a',
        version: '1.0.0',
    );
    $moduleB = new ModuleManifest(
        name: 'vendor/module-b',
        version: '1.0.0',
        require: ['vendor/module-a' => '^1.0'],
    );

    $resolver = new DependencyResolver();
    // Pass B first - should still load A first due to dependency
    $sorted = $resolver->resolve([$moduleB, $moduleA]);

    $names = array_map(fn (ModuleManifest $m) => $m->name, $sorted);

    expect($names)->toBe(['vendor/module-a', 'vendor/module-b']);
});

it('respects sequence after hints for load ordering', function (): void {
    // Module B wants to load after Module A (soft hint, not hard dependency)
    $moduleA = new ModuleManifest(
        name: 'vendor/module-a',
        version: '1.0.0',
    );
    $moduleB = new ModuleManifest(
        name: 'vendor/module-b',
        version: '1.0.0',
        after: ['vendor/module-a'],
    );

    $resolver = new DependencyResolver();
    // Pass B first - should still load A first due to after hint
    $sorted = $resolver->resolve([$moduleB, $moduleA]);

    $names = array_map(fn (ModuleManifest $m) => $m->name, $sorted);

    expect($names)->toBe(['vendor/module-a', 'vendor/module-b']);
});

it('respects sequence before hints for load ordering', function (): void {
    // Module A wants to load before Module B (soft hint)
    $moduleA = new ModuleManifest(
        name: 'vendor/module-a',
        version: '1.0.0',
        before: ['vendor/module-b'],
    );
    $moduleB = new ModuleManifest(
        name: 'vendor/module-b',
        version: '1.0.0',
    );

    $resolver = new DependencyResolver();
    // Pass B first - should still load A first due to before hint
    $sorted = $resolver->resolve([$moduleB, $moduleA]);

    $names = array_map(fn (ModuleManifest $m) => $m->name, $sorted);

    expect($names)->toBe(['vendor/module-a', 'vendor/module-b']);
});

it('throws CircularDependencyException when modules have circular require', function (): void {
    // A requires B, B requires A
    $moduleA = new ModuleManifest(
        name: 'vendor/module-a',
        version: '1.0.0',
        require: ['vendor/module-b' => '^1.0'],
    );
    $moduleB = new ModuleManifest(
        name: 'vendor/module-b',
        version: '1.0.0',
        require: ['vendor/module-a' => '^1.0'],
    );

    $resolver = new DependencyResolver();

    expect(fn () => $resolver->resolve([$moduleA, $moduleB]))
        ->toThrow(CircularDependencyException::class);
});

it('includes cycle path in CircularDependencyException message', function (): void {
    // A -> B -> C -> A (cycle)
    $moduleA = new ModuleManifest(
        name: 'vendor/module-a',
        version: '1.0.0',
        require: ['vendor/module-b' => '^1.0'],
    );
    $moduleB = new ModuleManifest(
        name: 'vendor/module-b',
        version: '1.0.0',
        require: ['vendor/module-c' => '^1.0'],
    );
    $moduleC = new ModuleManifest(
        name: 'vendor/module-c',
        version: '1.0.0',
        require: ['vendor/module-a' => '^1.0'],
    );

    $resolver = new DependencyResolver();

    try {
        $resolver->resolve([$moduleA, $moduleB, $moduleC]);
        $this->fail('Expected CircularDependencyException');
    } catch (CircularDependencyException $e) {
        // Message should contain the cycle path with arrows
        expect($e->getMessage())
            ->toContain('vendor/module-a')
            ->toContain('vendor/module-b')
            ->toContain('vendor/module-c')
            ->toContain('->');
    }
});

it('throws ModuleException when required module is not found', function (): void {
    // Module A requires a module that doesn't exist
    $moduleA = new ModuleManifest(
        name: 'vendor/module-a',
        version: '1.0.0',
        require: ['vendor/nonexistent' => '^1.0'],
    );

    $resolver = new DependencyResolver();

    expect(fn () => $resolver->resolve([$moduleA]))
        ->toThrow(ModuleException::class)
        ->and(fn () => $resolver->resolve([$moduleA]))
        ->toThrow(
            fn (ModuleException $e) => str_contains($e->getMessage(), 'vendor/nonexistent'),
        );
});

it('filters out disabled modules from load order', function (): void {
    $moduleA = new ModuleManifest(
        name: 'vendor/module-a',
        version: '1.0.0',
    );
    $moduleB = new ModuleManifest(
        name: 'vendor/module-b',
        version: '1.0.0',
        enabled: false, // Disabled
    );
    $moduleC = new ModuleManifest(
        name: 'vendor/module-c',
        version: '1.0.0',
    );

    $resolver = new DependencyResolver();
    $sorted = $resolver->resolve([$moduleA, $moduleB, $moduleC]);

    $names = array_map(fn (ModuleManifest $m) => $m->name, $sorted);

    // Module B should not be in the result
    expect($names)
        ->toBe(['vendor/module-a', 'vendor/module-c'])
        ->not->toContain('vendor/module-b');
});

it('throws ModuleException when enabled module requires disabled module', function (): void {
    // Module A (enabled) requires Module B (disabled)
    $moduleA = new ModuleManifest(
        name: 'vendor/module-a',
        version: '1.0.0',
        require: ['vendor/module-b' => '^1.0'],
    );
    $moduleB = new ModuleManifest(
        name: 'vendor/module-b',
        version: '1.0.0',
        enabled: false, // Disabled
    );

    $resolver = new DependencyResolver();

    try {
        $resolver->resolve([$moduleA, $moduleB]);
        $this->fail('Expected ModuleException');
    } catch (ModuleException $e) {
        expect($e->getMessage())
            ->toContain('vendor/module-a')
            ->toContain('vendor/module-b');
    }
});

it('handles complex dependency graphs correctly', function (): void {
    // Complex graph:
    // marko/core (no deps)
    // marko/database requires marko/core
    // marko/routing requires marko/core
    // acme/blog requires marko/database and wants to load after marko/routing
    // acme/admin requires acme/blog and marko/routing
    $core = new ModuleManifest(
        name: 'marko/core',
        version: '1.0.0',
    );
    $database = new ModuleManifest(
        name: 'marko/database',
        version: '1.0.0',
        require: ['marko/core' => '^1.0'],
    );
    $routing = new ModuleManifest(
        name: 'marko/routing',
        version: '1.0.0',
        require: ['marko/core' => '^1.0'],
    );
    $blog = new ModuleManifest(
        name: 'acme/blog',
        version: '1.0.0',
        require: ['marko/database' => '^1.0'],
        after: ['marko/routing'],
    );
    $admin = new ModuleManifest(
        name: 'acme/admin',
        version: '1.0.0',
        require: [
            'acme/blog' => '^1.0',
            'marko/routing' => '^1.0',
        ],
    );

    $resolver = new DependencyResolver();
    // Pass in random order
    $sorted = $resolver->resolve([$admin, $blog, $core, $routing, $database]);

    $names = array_map(fn (ModuleManifest $m) => $m->name, $sorted);

    // Verify correct ordering constraints:
    // core must be before database, routing
    // database must be before blog
    // routing must be before blog (due to after hint)
    // routing must be before admin
    // blog must be before admin
    $coreIndex = array_search('marko/core', $names);
    $databaseIndex = array_search('marko/database', $names);
    $routingIndex = array_search('marko/routing', $names);
    $blogIndex = array_search('acme/blog', $names);
    $adminIndex = array_search('acme/admin', $names);

    expect($coreIndex)->toBeLessThan($databaseIndex)
        ->and($coreIndex)->toBeLessThan($routingIndex)
        ->and($databaseIndex)->toBeLessThan($blogIndex)
        ->and($routingIndex)->toBeLessThan($blogIndex)
        ->and($routingIndex)->toBeLessThan($adminIndex)
        ->and($blogIndex)->toBeLessThan($adminIndex);
});

it('returns ModuleManifest objects in final load order', function (): void {
    $moduleA = new ModuleManifest(
        name: 'vendor/module-a',
        version: '1.0.0',
        path: '/path/to/module-a',
        source: 'vendor',
    );
    $moduleB = new ModuleManifest(
        name: 'vendor/module-b',
        version: '2.0.0',
        require: ['vendor/module-a' => '^1.0'],
        path: '/path/to/module-b',
        source: 'modules',
        bindings: ['SomeInterface' => 'SomeImplementation'],
    );

    $resolver = new DependencyResolver();
    $sorted = $resolver->resolve([$moduleB, $moduleA]);

    // Verify return type is array of ModuleManifest objects
    expect($sorted)->toBeArray()
        ->and($sorted)->toHaveCount(2)
        ->and($sorted[0])->toBeInstanceOf(ModuleManifest::class)
        ->and($sorted[1])->toBeInstanceOf(ModuleManifest::class);

    // Verify the manifests contain their original data
    expect($sorted[0]->name)->toBe('vendor/module-a')
        ->and($sorted[0]->version)->toBe('1.0.0')
        ->and($sorted[0]->path)->toBe('/path/to/module-a')
        ->and($sorted[0]->source)->toBe('vendor')
        ->and($sorted[1]->name)->toBe('vendor/module-b')
        ->and($sorted[1]->version)->toBe('2.0.0')
        ->and($sorted[1]->path)->toBe('/path/to/module-b')
        ->and($sorted[1]->source)->toBe('modules')
        ->and($sorted[1]->bindings)->toBe(['SomeInterface' => 'SomeImplementation']);
});
