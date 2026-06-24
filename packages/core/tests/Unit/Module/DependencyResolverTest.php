<?php

declare(strict_types=1);

use Marko\Core\Exceptions\CircularDependencyException;
use Marko\Core\Exceptions\MissingDependencyException;
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

    $exception = null;

    try {
        $resolver->resolve([$moduleA, $moduleB, $moduleC]);
    } catch (CircularDependencyException $e) {
        $exception = $e;
    }

    // Message should contain the cycle path with arrows
    expect($exception)->not->toBeNull()
        ->and($exception->getMessage())
        ->toContain('vendor/module-a')
        ->toContain('vendor/module-b')
        ->toContain('vendor/module-c')
        ->toContain('->');
});

it('ignores dependencies that are not Marko modules', function (): void {
    // Module A requires a package that isn't in our modules list
    // This could be a regular Composer package like psr/container
    $moduleA = new ModuleManifest(
        name: 'vendor/module-a',
        version: '1.0.0',
        require: ['psr/container' => '^2.0'],
    );

    $resolver = new DependencyResolver();
    $sorted = $resolver->resolve([$moduleA]);

    // Should resolve successfully - non-Marko dependencies are ignored
    expect($sorted)->toHaveCount(1)
        ->and($sorted[0]->name)->toBe('vendor/module-a');
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

it('throws MissingDependencyException when enabled module requires disabled module', function (): void {
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

    $exception = null;

    try {
        $resolver->resolve([$moduleA, $moduleB]);
    } catch (MissingDependencyException $e) {
        $exception = $e;
    }

    expect($exception)->not->toBeNull()
        ->and($exception->getMessage())
        ->toContain('vendor/module-a')
        ->toContain('vendor/module-b');
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

    // Verify return type is array of ModuleManifest objects with their original data
    expect($sorted)->toBeArray()
        ->and($sorted)->toHaveCount(2)
        ->and($sorted[0])->toBeInstanceOf(ModuleManifest::class)
        ->and($sorted[1])->toBeInstanceOf(ModuleManifest::class)
        ->and($sorted[0]->name)->toBe('vendor/module-a')
        ->and($sorted[0]->version)->toBe('1.0.0')
        ->and($sorted[0]->path)->toBe('/path/to/module-a')
        ->and($sorted[0]->source)->toBe('vendor')
        ->and($sorted[1]->name)->toBe('vendor/module-b')
        ->and($sorted[1]->version)->toBe('2.0.0')
        ->and($sorted[1]->path)->toBe('/path/to/module-b')
        ->and($sorted[1]->source)->toBe('modules')
        ->and($sorted[1]->bindings)->toBe(['SomeInterface' => 'SomeImplementation']);
});

it('states the dependency is present but disabled rather than not installed', function (): void {
    $moduleA = new ModuleManifest(
        name: 'vendor/module-a',
        version: '1.0.0',
        require: ['vendor/module-b' => '^1.0'],
    );
    $moduleB = new ModuleManifest(
        name: 'vendor/module-b',
        version: '1.0.0',
        enabled: false,
    );

    $resolver = new DependencyResolver();

    $exception = null;

    try {
        $resolver->resolve([$moduleA, $moduleB]);
    } catch (MissingDependencyException $e) {
        $exception = $e;
    }

    expect($exception)->not->toBeNull()
        ->and($exception->getMessage())
        ->toContain('present but disabled')
        ->not->toContain('not installed');
});

it('throws a missing dependency error (not an empty-chain circular error) for a before-after soft-ordering deadlock', function (): void {
    // Module A declares both after: [B] and before: [B] — an unsatisfiable ordering deadlock
    $moduleA = new ModuleManifest(
        name: 'vendor/module-a',
        version: '1.0.0',
        after: ['vendor/module-b'],
        before: ['vendor/module-b'],
    );
    $moduleB = new ModuleManifest(
        name: 'vendor/module-b',
        version: '1.0.0',
    );

    $resolver = new DependencyResolver();

    expect(fn () => $resolver->resolve([$moduleA, $moduleB]))
        ->toThrow(MissingDependencyException::class);
});

it('names the unsorted modules and their unmet ordering constraints in the missing-dependency message', function (): void {
    // Module A declares both after: [B] and before: [B] — unsatisfiable
    $moduleA = new ModuleManifest(
        name: 'vendor/module-a',
        version: '1.0.0',
        after: ['vendor/module-b'],
        before: ['vendor/module-b'],
    );
    $moduleB = new ModuleManifest(
        name: 'vendor/module-b',
        version: '1.0.0',
    );

    $resolver = new DependencyResolver();

    $exception = null;

    try {
        $resolver->resolve([$moduleA, $moduleB]);
    } catch (MissingDependencyException $e) {
        $exception = $e;
    }

    expect($exception)->not->toBeNull()
        ->and($exception->getMessage())
        ->toContain('vendor/module-a')
        ->toContain('after:vendor/module-b')
        ->toContain('before:vendor/module-b');
});

it('throws a circular dependency error with a populated chain for a real two-module require cycle', function (): void {
    // A requires B, B requires A — a real cycle via require
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

    $exception = null;

    try {
        $resolver->resolve([$moduleA, $moduleB]);
    } catch (CircularDependencyException $e) {
        $exception = $e;
    }

    expect($exception)->not->toBeNull()
        ->and($exception->getMessage())
        ->toContain('vendor/module-a')
        ->toContain('vendor/module-b')
        ->toContain('->');
});

it('includes every node of the cycle in the chain for a real three-module require cycle', function (): void {
    // A requires B, B requires C, C requires A
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

    $exception = null;

    try {
        $resolver->resolve([$moduleA, $moduleB, $moduleC]);
    } catch (CircularDependencyException $e) {
        $exception = $e;
    }

    expect($exception)->not->toBeNull()
        ->and($exception->getMessage())
        ->toContain('vendor/module-a')
        ->toContain('vendor/module-b')
        ->toContain('vendor/module-c')
        ->toContain('->');
});

it('resolves successfully and throws nothing when every dependency is satisfied', function (): void {
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
    $sorted = $resolver->resolve([$moduleB, $moduleA]);

    $names = array_map(fn (ModuleManifest $m) => $m->name, $sorted);

    expect($names)->toBe(['vendor/module-a', 'vendor/module-b']);
});

it('still resolves successfully when an enabled module requires a non-marko composer package (absent from the module list)', function (): void {
    $moduleA = new ModuleManifest(
        name: 'vendor/module-a',
        version: '1.0.0',
        require: ['psr/container' => '^2.0', 'symfony/http-foundation' => '^6.0'],
    );

    $resolver = new DependencyResolver();
    $sorted = $resolver->resolve([$moduleA]);

    expect($sorted)->toHaveCount(1)
        ->and($sorted[0]->name)->toBe('vendor/module-a');
});

it('throws a missing dependency error naming a module that requires a disabled dependency', function (): void {
    $moduleA = new ModuleManifest(
        name: 'vendor/module-a',
        version: '1.0.0',
        require: ['vendor/module-b' => '^1.0'],
    );
    $moduleB = new ModuleManifest(
        name: 'vendor/module-b',
        version: '1.0.0',
        enabled: false,
    );

    $resolver = new DependencyResolver();

    expect(fn () => $resolver->resolve([$moduleA, $moduleB]))
        ->toThrow(MissingDependencyException::class);
});
