<?php

declare(strict_types=1);

use Marko\Core\Exceptions\ModuleException;
use Marko\Core\Module\GlobalMiddlewareResolver;
use Marko\Core\Module\ModuleManifest;
use Marko\Routing\Middleware\MiddlewareInterface;

// ---------------------------------------------------------------------------
// Minimal stub middleware for testing (avoids needing real middleware classes)
// ---------------------------------------------------------------------------

function makeMiddlewareClass(string $className): void
{
    if (class_exists($className)) {
        return;
    }

    $parts = explode('\\', $className);
    $shortName = array_pop($parts);
    $namespace = implode('\\', $parts);

    eval(
        "namespace $namespace; " .
        "class $shortName implements \\" . MiddlewareInterface::class . " { " .
        "public function handle(\Marko\Routing\Http\Request \$request, callable \$next): \Marko\Routing\Http\Response { return \$next(\$request); } " .
        "}"
    );
}

function makeModuleManifest(
    string $name,
    string $source,
    array $globalMiddleware = [],
): ModuleManifest {
    return new ModuleManifest(
        name: $name,
        version: '1.0.0',
        source: $source,
        globalMiddleware: $globalMiddleware,
    );
}

// ---------------------------------------------------------------------------
// Schema: class-string entries only (no priority numbers)
// ---------------------------------------------------------------------------

it('accepts globalMiddleware as a flat list of class strings', function (): void {
    makeMiddlewareClass('Acme\Mw\AlphaMiddleware');
    makeMiddlewareClass('Acme\Mw\BetaMiddleware');

    $module = makeModuleManifest(
        name: 'acme/test',
        source: 'vendor',
        globalMiddleware: [
            'Acme\Mw\AlphaMiddleware',
            'Acme\Mw\BetaMiddleware',
        ],
    );

    $result = (new GlobalMiddlewareResolver())->resolve([$module]);

    expect($result)->toBe([
        'Acme\Mw\AlphaMiddleware',
        'Acme\Mw\BetaMiddleware',
    ]);
});

it('rejects array-form entries with a helpful exception', function (): void {
    makeMiddlewareClass('Acme\Mw\ArrayFormMiddleware');

    $module = makeModuleManifest(
        name: 'acme/test',
        source: 'vendor',
        globalMiddleware: [
            ['class' => 'Acme\Mw\ArrayFormMiddleware', 'priority' => 10],
        ],
    );

    expect(fn () => (new GlobalMiddlewareResolver())->resolve([$module]))
        ->toThrow(
            ModuleException::class,
            "Invalid globalMiddleware entry in module 'acme/test'",
        );
});

// ---------------------------------------------------------------------------
// Ordering: module load order, then declaration order within a module
// ---------------------------------------------------------------------------

it('preserves declaration order within a single module', function (): void {
    makeMiddlewareClass('Acme\Mw\FirstMiddleware');
    makeMiddlewareClass('Acme\Mw\SecondMiddleware');
    makeMiddlewareClass('Acme\Mw\ThirdMiddleware');

    $module = makeModuleManifest(
        name: 'acme/test',
        source: 'vendor',
        globalMiddleware: [
            'Acme\Mw\FirstMiddleware',
            'Acme\Mw\SecondMiddleware',
            'Acme\Mw\ThirdMiddleware',
        ],
    );

    $result = (new GlobalMiddlewareResolver())->resolve([$module]);

    expect($result)->toBe([
        'Acme\Mw\FirstMiddleware',
        'Acme\Mw\SecondMiddleware',
        'Acme\Mw\ThirdMiddleware',
    ]);
});

it('preserves module load order across modules', function (): void {
    // Modules arrive already topologically sorted by DependencyResolver.
    // The resolver must preserve that order, not re-sort by anything else.
    makeMiddlewareClass('Acme\Mw\PageCacheMiddleware');
    makeMiddlewareClass('Acme\Mw\SessionMiddleware');
    makeMiddlewareClass('Acme\Mw\LayoutMiddleware');

    $modules = [
        makeModuleManifest(
            name: 'acme/page-cache',
            source: 'vendor',
            globalMiddleware: ['Acme\Mw\PageCacheMiddleware'],
        ),
        makeModuleManifest(
            name: 'acme/session',
            source: 'vendor',
            globalMiddleware: ['Acme\Mw\SessionMiddleware'],
        ),
        makeModuleManifest(
            name: 'acme/layout',
            source: 'vendor',
            globalMiddleware: ['Acme\Mw\LayoutMiddleware'],
        ),
    ];

    $result = (new GlobalMiddlewareResolver())->resolve($modules);

    expect($result)->toBe([
        'Acme\Mw\PageCacheMiddleware',
        'Acme\Mw\SessionMiddleware',
        'Acme\Mw\LayoutMiddleware',
    ]);
});

it('merges globalMiddleware from multiple modules in module order', function (): void {
    makeMiddlewareClass('Acme\Mw\ModuleAFirst');
    makeMiddlewareClass('Acme\Mw\ModuleASecond');
    makeMiddlewareClass('Acme\Mw\ModuleBOnly');

    $modules = [
        makeModuleManifest(
            name: 'acme/a',
            source: 'vendor',
            globalMiddleware: ['Acme\Mw\ModuleAFirst', 'Acme\Mw\ModuleASecond'],
        ),
        makeModuleManifest(
            name: 'acme/b',
            source: 'vendor',
            globalMiddleware: ['Acme\Mw\ModuleBOnly'],
        ),
    ];

    $result = (new GlobalMiddlewareResolver())->resolve($modules);

    expect($result)->toBe([
        'Acme\Mw\ModuleAFirst',
        'Acme\Mw\ModuleASecond',
        'Acme\Mw\ModuleBOnly',
    ]);
});

// ---------------------------------------------------------------------------
// Deduplication: app > modules > vendor
// ---------------------------------------------------------------------------

it('deduplicates by class string, emitting each middleware once', function (): void {
    makeMiddlewareClass('Acme\Mw\DupedMiddleware');

    $modules = [
        makeModuleManifest(
            name: 'acme/first',
            source: 'vendor',
            globalMiddleware: ['Acme\Mw\DupedMiddleware'],
        ),
        makeModuleManifest(
            name: 'acme/second',
            source: 'vendor',
            globalMiddleware: ['Acme\Mw\DupedMiddleware'],
        ),
    ];

    $result = (new GlobalMiddlewareResolver())->resolve($modules);

    expect($result)->toBe(['Acme\Mw\DupedMiddleware']);
});

it('lets a higher-source declaration override the position of a lower-source one', function (): void {
    // vendor declares A then B. App redeclares A after B — app wins on
    // position, so the final order becomes [B, A].
    makeMiddlewareClass('Acme\Mw\OverrideA');
    makeMiddlewareClass('Acme\Mw\OverrideB');

    $modules = [
        makeModuleManifest(
            name: 'acme/vendor-pkg',
            source: 'vendor',
            globalMiddleware: ['Acme\Mw\OverrideA', 'Acme\Mw\OverrideB'],
        ),
        makeModuleManifest(
            name: 'acme/app-module',
            source: 'app',
            globalMiddleware: ['Acme\Mw\OverrideA'],
        ),
    ];

    $result = (new GlobalMiddlewareResolver())->resolve($modules);

    expect($result)->toBe([
        'Acme\Mw\OverrideB',
        'Acme\Mw\OverrideA',
    ]);
});

it('does not let a lower-source declaration override a higher-source position', function (): void {
    makeMiddlewareClass('Acme\Mw\KeepAppPosition');
    makeMiddlewareClass('Acme\Mw\AfterIt');

    $modules = [
        makeModuleManifest(
            name: 'acme/app-module',
            source: 'app',
            globalMiddleware: ['Acme\Mw\KeepAppPosition'],
        ),
        makeModuleManifest(
            name: 'acme/vendor-pkg',
            source: 'vendor',
            globalMiddleware: ['Acme\Mw\KeepAppPosition', 'Acme\Mw\AfterIt'],
        ),
    ];

    $result = (new GlobalMiddlewareResolver())->resolve($modules);

    expect($result)->toBe([
        'Acme\Mw\KeepAppPosition',
        'Acme\Mw\AfterIt',
    ]);
});

// ---------------------------------------------------------------------------
// Validation: loud errors for bad declarations
// ---------------------------------------------------------------------------

it('throws a clear exception when a declared class does not exist', function (): void {
    $module = makeModuleManifest(
        name: 'acme/test',
        source: 'vendor',
        globalMiddleware: ['Acme\Mw\NonExistentMiddleware'],
    );

    expect(fn () => (new GlobalMiddlewareResolver())->resolve([$module]))
        ->toThrow(
            ModuleException::class,
            "Class 'Acme\\Mw\\NonExistentMiddleware' does not exist",
        );
});

it('throws a clear exception when a declared class does not implement MiddlewareInterface', function (): void {
    eval('namespace Acme\\Mw; class NotAMiddleware {}');

    $module = makeModuleManifest(
        name: 'acme/test',
        source: 'vendor',
        globalMiddleware: ['Acme\Mw\NotAMiddleware'],
    );

    expect(fn () => (new GlobalMiddlewareResolver())->resolve([$module]))
        ->toThrow(
            ModuleException::class,
            'does not implement Marko\\Routing\\Middleware\\MiddlewareInterface',
        );
});

it('throws a clear exception when an entry is not a string', function (): void {
    $module = makeModuleManifest(
        name: 'acme/test',
        source: 'vendor',
        globalMiddleware: [123],
    );

    expect(fn () => (new GlobalMiddlewareResolver())->resolve([$module]))
        ->toThrow(
            ModuleException::class,
            "Invalid globalMiddleware entry in module 'acme/test'",
        );
});

// ---------------------------------------------------------------------------
// Empty cases
// ---------------------------------------------------------------------------

it('returns empty array when modules exist but none declare globalMiddleware', function (): void {
    $modules = [
        makeModuleManifest(name: 'acme/empty-one', source: 'vendor'),
        makeModuleManifest(name: 'acme/empty-two', source: 'modules'),
    ];

    expect((new GlobalMiddlewareResolver())->resolve($modules))->toBe([]);
});

it('returns empty array when no modules are loaded', function (): void {
    expect((new GlobalMiddlewareResolver())->resolve([]))->toBe([]);
});
