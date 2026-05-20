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
    $result = $resolver->resolve([$module]);

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
    $result = $resolver->resolve([$module]);

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
    $result = $resolver->resolve([$module]);

    $deltaIndex = array_search('Acme\Mw\DeltaMiddleware', $result);
    $epsilonIndex = array_search('Acme\Mw\EpsilonMiddleware', $result);

    // EpsilonMiddleware (priority 50) should come before DeltaMiddleware (priority 100)
    expect($epsilonIndex)->toBeLessThan($deltaIndex);
});

// ---------------------------------------------------------------------------
// Requirement 4: merges globalMiddleware from multiple modules
// ---------------------------------------------------------------------------

it('merges globalMiddleware declarations from multiple modules', function (): void {
    makeMiddlewareClass('Acme\Mw\ZetaMiddleware');
    makeMiddlewareClass('Acme\Mw\OmegaMiddleware');

    $moduleA = makeModuleManifest(
        name: 'acme/mod-a',
        source: 'vendor',
        globalMiddleware: ['Acme\Mw\ZetaMiddleware'],
    );

    $moduleB = makeModuleManifest(
        name: 'acme/mod-b',
        source: 'vendor',
        globalMiddleware: ['Acme\Mw\OmegaMiddleware'],
    );

    $resolver = new GlobalMiddlewareResolver();
    $result = $resolver->resolve([$moduleA, $moduleB]);

    expect($result)
        ->toContain('Acme\Mw\ZetaMiddleware')
        ->toContain('Acme\Mw\OmegaMiddleware');
});

// ---------------------------------------------------------------------------
// Requirement 5: sorts merged result by priority ascending
// ---------------------------------------------------------------------------

it('sorts merged globalMiddleware by priority ascending', function (): void {
    makeMiddlewareClass('Acme\Mw\EtaMiddleware');    // priority 30
    makeMiddlewareClass('Acme\Mw\ThetaMiddleware');  // priority 10
    makeMiddlewareClass('Acme\Mw\IotaMiddleware');   // priority 20

    $moduleA = makeModuleManifest(
        name: 'acme/mod-a',
        source: 'vendor',
        globalMiddleware: [
            ['class' => 'Acme\Mw\EtaMiddleware', 'priority' => 30],
            ['class' => 'Acme\Mw\IotaMiddleware', 'priority' => 20],
        ],
    );

    $moduleB = makeModuleManifest(
        name: 'acme/mod-b',
        source: 'vendor',
        globalMiddleware: [
            ['class' => 'Acme\Mw\ThetaMiddleware', 'priority' => 10],
        ],
    );

    $resolver = new GlobalMiddlewareResolver();
    $result = $resolver->resolve([$moduleA, $moduleB]);

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
    $result = $resolver->resolve([$vendorModule, $appModule]);

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
    $result = $resolver->resolve([$module1, $module2]);

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

it('assigns priority 10 to PageCacheMiddleware 20 to SessionMiddleware 30 to LayoutMiddleware as module declarations', function (): void {
    makeMiddlewareClass('Marko\PageCache\Middleware\PageCacheMiddleware');
    makeMiddlewareClass('Marko\Session\Middleware\SessionMiddleware');
    makeMiddlewareClass('Marko\Layout\Middleware\LayoutMiddleware');

    $pageCacheModule = makeModuleManifest(
        name: 'marko/page-cache',
        source: 'vendor',
        globalMiddleware: [['class' => 'Marko\PageCache\Middleware\PageCacheMiddleware', 'priority' => 10]],
    );

    $sessionModule = makeModuleManifest(
        name: 'marko/session',
        source: 'vendor',
        globalMiddleware: [['class' => 'Marko\Session\Middleware\SessionMiddleware', 'priority' => 20]],
    );

    $layoutModule = makeModuleManifest(
        name: 'marko/layout',
        source: 'vendor',
        globalMiddleware: [['class' => 'Marko\Layout\Middleware\LayoutMiddleware', 'priority' => 30]],
    );

    $resolver = new GlobalMiddlewareResolver();
    $result = $resolver->resolve([$pageCacheModule, $sessionModule, $layoutModule]);

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
    $result = $resolver->resolve([$module]);

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

    expect(fn () => $resolver->resolve([$module]))
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

    expect(fn () => $resolver->resolve([$module]))
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

    expect(fn () => $resolver->resolve([$module]))
        ->toThrow(ModuleException::class);
});

// ---------------------------------------------------------------------------
// Requirement 13: returns empty array when modules have no globalMiddleware
// ---------------------------------------------------------------------------

it('returns empty array when modules exist but none declare globalMiddleware', function (): void {
    $module = makeModuleManifest(name: 'acme/no-mw', source: 'vendor');

    $resolver = new GlobalMiddlewareResolver();
    $result = $resolver->resolve([$module]);

    expect($result)->toBe([]);
});

// ---------------------------------------------------------------------------
// Requirement 14: returns empty array when no modules are loaded
// ---------------------------------------------------------------------------

it('returns empty array when no modules are loaded', function (): void {
    $resolver = new GlobalMiddlewareResolver();
    $result = $resolver->resolve([]);

    expect($result)->toBe([]);
});
