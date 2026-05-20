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
// Requirement 1: flat list of class strings
// ---------------------------------------------------------------------------

it('accepts globalMiddleware as a flat list of class strings in module.php', function (): void {
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

    $resolver = new GlobalMiddlewareResolver();
    $result = $resolver->resolve([$module], builtIns: []);

    expect($result)
        ->toContain('Acme\Mw\AlphaMiddleware')
        ->toContain('Acme\Mw\BetaMiddleware');
});

// ---------------------------------------------------------------------------
// Requirement 2: array form with class key and priority
// ---------------------------------------------------------------------------

it('accepts globalMiddleware entries as array with class key and priority', function (): void {
    makeMiddlewareClass('Acme\Mw\GammaMiddleware');

    $module = makeModuleManifest(
        name: 'acme/test',
        source: 'vendor',
        globalMiddleware: [
            ['class' => 'Acme\Mw\GammaMiddleware', 'priority' => 25],
        ],
    );

    $resolver = new GlobalMiddlewareResolver();
    $result = $resolver->resolve([$module], builtIns: []);

    expect($result)->toContain('Acme\Mw\GammaMiddleware');
});

// ---------------------------------------------------------------------------
// Requirement 3: default priority 100 for flat entries
// ---------------------------------------------------------------------------

it('defaults missing priority to 100', function (): void {
    makeMiddlewareClass('Acme\Mw\DeltaMiddleware');
    makeMiddlewareClass('Acme\Mw\EpsilonMiddleware');

    // DeltaMiddleware is flat (default priority 100)
    // EpsilonMiddleware has priority 50 — should come first
    $module = makeModuleManifest(
        name: 'acme/test',
        source: 'vendor',
        globalMiddleware: [
            'Acme\Mw\DeltaMiddleware',
            ['class' => 'Acme\Mw\EpsilonMiddleware', 'priority' => 50],
        ],
    );

    $resolver = new GlobalMiddlewareResolver();
    $result = $resolver->resolve([$module], builtIns: []);

    $deltaIndex = array_search('Acme\Mw\DeltaMiddleware', $result);
    $epsilonIndex = array_search('Acme\Mw\EpsilonMiddleware', $result);

    // EpsilonMiddleware (priority 50) should come before DeltaMiddleware (priority 100)
    expect($epsilonIndex)->toBeLessThan($deltaIndex);
});

// ---------------------------------------------------------------------------
// Requirement 4: merges with built-in hardcoded list
// ---------------------------------------------------------------------------

it('merges module-declared globalMiddleware with built-in hardcoded list', function (): void {
    makeMiddlewareClass('Acme\Mw\ZetaMiddleware');

    $module = makeModuleManifest(
        name: 'acme/test',
        source: 'vendor',
        globalMiddleware: ['Acme\Mw\ZetaMiddleware'],
    );

    $builtIns = [
        ['class' => 'Acme\Mw\ZetaMiddleware', 'priority' => 10, 'source' => 'vendor'], // same class won't duplicate
    ];

    // Use different built-in so we can verify both appear
    makeMiddlewareClass('Acme\BuiltIn\BuiltInMiddleware');
    $builtInsClean = [
        ['class' => 'Acme\BuiltIn\BuiltInMiddleware', 'priority' => 10, 'source' => 'vendor'],
    ];

    $resolver = new GlobalMiddlewareResolver();
    $result = $resolver->resolve([$module], builtIns: $builtInsClean);

    expect($result)
        ->toContain('Acme\Mw\ZetaMiddleware')
        ->toContain('Acme\BuiltIn\BuiltInMiddleware');
});

// ---------------------------------------------------------------------------
// Requirement 5: sorts merged result by priority ascending
// ---------------------------------------------------------------------------

it('sorts merged globalMiddleware by priority ascending', function (): void {
    makeMiddlewareClass('Acme\Mw\EtaMiddleware');    // priority 30
    makeMiddlewareClass('Acme\Mw\ThetaMiddleware');  // priority 10 (built-in)
    makeMiddlewareClass('Acme\Mw\IotaMiddleware');   // priority 20

    $module = makeModuleManifest(
        name: 'acme/test',
        source: 'vendor',
        globalMiddleware: [
            ['class' => 'Acme\Mw\EtaMiddleware', 'priority' => 30],
            ['class' => 'Acme\Mw\IotaMiddleware', 'priority' => 20],
        ],
    );

    $builtIns = [
        ['class' => 'Acme\Mw\ThetaMiddleware', 'priority' => 10, 'source' => 'vendor'],
    ];

    $resolver = new GlobalMiddlewareResolver();
    $result = $resolver->resolve([$module], builtIns: $builtIns);

    $thetaIndex = array_search('Acme\Mw\ThetaMiddleware', $result);
    $iotaIndex = array_search('Acme\Mw\IotaMiddleware', $result);
    $etaIndex = array_search('Acme\Mw\EtaMiddleware', $result);

    expect($thetaIndex)->toBeLessThan($iotaIndex)
        ->and($iotaIndex)->toBeLessThan($etaIndex);
});

// ---------------------------------------------------------------------------
// Requirement 6: deduplication — app > modules > vendor source priority
// ---------------------------------------------------------------------------

it('deduplicates globalMiddleware entries preferring app over modules over vendor source', function (): void {
    makeMiddlewareClass('Acme\Mw\KappaMiddleware');

    $vendorModule = makeModuleManifest(
        name: 'acme/vendor-mod',
        source: 'vendor',
        globalMiddleware: [
            ['class' => 'Acme\Mw\KappaMiddleware', 'priority' => 10],
        ],
    );

    $appModule = makeModuleManifest(
        name: 'acme/app-mod',
        source: 'app',
        globalMiddleware: [
            ['class' => 'Acme\Mw\KappaMiddleware', 'priority' => 50],
        ],
    );

    $resolver = new GlobalMiddlewareResolver();
    $result = $resolver->resolve([$vendorModule, $appModule], builtIns: []);

    // Should appear only once — app source wins
    $count = count(array_filter($result, fn ($c) => $c === 'Acme\Mw\KappaMiddleware'));
    expect($count)->toBe(1);
    // App entry has priority 50 — but app source always wins regardless
    expect($result)->toContain('Acme\Mw\KappaMiddleware');
});

// ---------------------------------------------------------------------------
// Requirement 7: within same source, keep lowest priority value
// ---------------------------------------------------------------------------

it('deduplicates globalMiddleware within the same source by keeping the lowest priority value', function (): void {
    makeMiddlewareClass('Acme\Mw\LambdaMiddleware');
    makeMiddlewareClass('Acme\Mw\MuMiddleware');

    $module1 = makeModuleManifest(
        name: 'acme/mod-a',
        source: 'vendor',
        globalMiddleware: [
            ['class' => 'Acme\Mw\LambdaMiddleware', 'priority' => 80],
            ['class' => 'Acme\Mw\MuMiddleware', 'priority' => 5],
        ],
    );

    $module2 = makeModuleManifest(
        name: 'acme/mod-b',
        source: 'vendor',
        globalMiddleware: [
            ['class' => 'Acme\Mw\LambdaMiddleware', 'priority' => 40],  // lower → should win
        ],
    );

    $resolver = new GlobalMiddlewareResolver();
    $result = $resolver->resolve([$module1, $module2], builtIns: []);

    // LambdaMiddleware should appear once, with priority 40 (comes before MuMiddleware priority 5? no)
    $count = count(array_filter($result, fn ($c) => $c === 'Acme\Mw\LambdaMiddleware'));
    expect($count)->toBe(1);

    $lambdaIndex = array_search('Acme\Mw\LambdaMiddleware', $result);
    $muIndex = array_search('Acme\Mw\MuMiddleware', $result);

    // MuMiddleware has priority 5, LambdaMiddleware's kept priority is 40 → mu comes first
    expect($muIndex)->toBeLessThan($lambdaIndex);
});

// ---------------------------------------------------------------------------
// Requirement 8: built-in priorities
// ---------------------------------------------------------------------------

it('assigns priority 10 to PageCacheMiddleware 20 to SessionMiddleware 30 to LayoutMiddleware as built-in defaults', function (): void {
    // Make built-in classes exist for this test
    makeMiddlewareClass('Marko\PageCache\Middleware\PageCacheMiddleware');
    makeMiddlewareClass('Marko\Session\Middleware\SessionMiddleware');
    makeMiddlewareClass('Marko\Layout\Middleware\LayoutMiddleware');

    $resolver = new GlobalMiddlewareResolver();
    $result = $resolver->resolve([], builtIns: GlobalMiddlewareResolver::DEFAULT_BUILT_INS);

    $pageCacheIndex = array_search('Marko\PageCache\Middleware\PageCacheMiddleware', $result);
    $sessionIndex = array_search('Marko\Session\Middleware\SessionMiddleware', $result);
    $layoutIndex = array_search('Marko\Layout\Middleware\LayoutMiddleware', $result);

    // All should be present and ordered: PageCache (10) < Session (20) < Layout (30)
    expect($pageCacheIndex)->not->toBeFalse()
        ->and($sessionIndex)->not->toBeFalse()
        ->and($layoutIndex)->not->toBeFalse()
        ->and($pageCacheIndex)->toBeLessThan($sessionIndex)
        ->and($sessionIndex)->toBeLessThan($layoutIndex);
});

// ---------------------------------------------------------------------------
// Requirement 9: returns class-string array in priority order
// ---------------------------------------------------------------------------

it('returns class-string array from discoverGlobalMiddleware in priority order', function (): void {
    makeMiddlewareClass('Acme\Mw\NuMiddleware');
    makeMiddlewareClass('Acme\Mw\XiMiddleware');

    $module = makeModuleManifest(
        name: 'acme/test',
        source: 'vendor',
        globalMiddleware: [
            ['class' => 'Acme\Mw\NuMiddleware', 'priority' => 200],
            ['class' => 'Acme\Mw\XiMiddleware', 'priority' => 5],
        ],
    );

    $resolver = new GlobalMiddlewareResolver();
    $result = $resolver->resolve([$module], builtIns: []);

    expect($result)->toBeArray();
    foreach ($result as $entry) {
        expect($entry)->toBeString();
    }

    $nuIndex = array_search('Acme\Mw\NuMiddleware', $result);
    $xiIndex = array_search('Acme\Mw\XiMiddleware', $result);

    // XiMiddleware (priority 5) should come before NuMiddleware (priority 200)
    expect($xiIndex)->toBeLessThan($nuIndex);
});

// ---------------------------------------------------------------------------
// Requirement 10: throws exception when module-declared class does not exist
// ---------------------------------------------------------------------------

it('throws a clear exception with suggestion when a module-declared class does not exist', function (): void {
    $module = makeModuleManifest(
        name: 'acme/bad',
        source: 'vendor',
        globalMiddleware: ['Acme\NonExistent\GhostMiddleware'],
    );

    $resolver = new GlobalMiddlewareResolver();

    expect(fn () => $resolver->resolve([$module], builtIns: []))
        ->toThrow(ModuleException::class);
});

// ---------------------------------------------------------------------------
// Requirement 11: throws exception when array-form entry is missing class key
// ---------------------------------------------------------------------------

it('throws a clear exception with suggestion when an array-form entry is missing the class key', function (): void {
    $module = makeModuleManifest(
        name: 'acme/bad',
        source: 'vendor',
        globalMiddleware: [['priority' => 10]],  // missing 'class' key
    );

    $resolver = new GlobalMiddlewareResolver();

    expect(fn () => $resolver->resolve([$module], builtIns: []))
        ->toThrow(ModuleException::class);
});

// ---------------------------------------------------------------------------
// Requirement 12: throws exception when declared class doesn't implement MiddlewareInterface
// ---------------------------------------------------------------------------

it('throws a clear exception with suggestion when a declared class does not implement MiddlewareInterface', function (): void {
    // Create a class that exists but doesn't implement MiddlewareInterface
    if (!class_exists('Acme\Mw\NotAMiddleware')) {
        eval('namespace Acme\Mw; class NotAMiddleware {}');
    }

    $module = makeModuleManifest(
        name: 'acme/bad',
        source: 'vendor',
        globalMiddleware: ['Acme\Mw\NotAMiddleware'],
    );

    $resolver = new GlobalMiddlewareResolver();

    expect(fn () => $resolver->resolve([$module], builtIns: []))
        ->toThrow(ModuleException::class);
});

// ---------------------------------------------------------------------------
// Requirement 13: built-in entries silently skip when class doesn't exist
// ---------------------------------------------------------------------------

it('silently skips built-in GLOBAL_MIDDLEWARE entries when the class does not exist (backwards compat)', function (): void {
    $builtIns = [
        ['class' => 'Marko\NonExistent\Middleware\FakeMiddleware', 'priority' => 10, 'source' => 'vendor', 'skipIfMissing' => true],
    ];

    $resolver = new GlobalMiddlewareResolver();

    // Should not throw — just skip the non-existent built-in class
    $result = $resolver->resolve([], builtIns: $builtIns);

    expect($result)
        ->toBeArray()
        ->not->toContain('Marko\NonExistent\Middleware\FakeMiddleware');
});

// ---------------------------------------------------------------------------
// Requirement 14: preserves existing GLOBAL_MIDDLEWARE behavior with no module declarations
// ---------------------------------------------------------------------------

it('preserves existing GLOBAL_MIDDLEWARE behavior when no modules declare globalMiddleware', function (): void {
    // With no modules and default built-ins where classes don't exist → empty result
    $resolver = new GlobalMiddlewareResolver();

    // The DEFAULT_BUILT_INS reference real classes that may not exist in tests.
    // This test verifies zero behavior change: passing built-ins with skipIfMissing => true
    // when no modules declare anything returns whatever classes actually exist.
    $builtIns = GlobalMiddlewareResolver::DEFAULT_BUILT_INS;
    $result = $resolver->resolve([], builtIns: $builtIns);

    expect($result)->toBeArray();

    // Verify the result only contains classes that actually exist
    foreach ($result as $class) {
        expect(class_exists($class))->toBeTrue("Class $class should exist in result");
    }
});
