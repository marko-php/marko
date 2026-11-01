<?php

declare(strict_types=1);

use Marko\Authorization\Attributes\GateHook;
use Marko\Authorization\Discovery\GateHookCacheContributor;
use Marko\Authorization\Discovery\GateHookDefinition;
use Marko\Authorization\Discovery\GateHookDiscovery;
use Marko\Authorization\Exceptions\GateHookException;
use Marko\Core\Exceptions\DiscoveryCacheException;
use Marko\Core\Module\ModuleManifest;

/**
 * A temporary module whose src/ holds the given files (name => PHP source).
 *
 * @param array<string, string> $files
 */
function gateHookTestModule(
    string $name,
    array $files,
): ModuleManifest {
    $path = sys_get_temp_dir() . '/marko-gate-hook-test-' . bin2hex(random_bytes(8));
    mkdir($path . '/src', 0755, true);

    foreach ($files as $file => $source) {
        file_put_contents("$path/src/$file", $source);
    }

    return new ModuleManifest(name: $name, version: '1.0.0', path: $path);
}

function removeGateHookTestModule(
    ModuleManifest $module,
): void {
    array_map('unlink', glob($module->path . '/src/*.php') ?: []);
    rmdir($module->path . '/src');
    rmdir($module->path);
}

function gateHookSource(
    string $namespace,
    string $class,
    ?int $priority,
    bool $implements = true,
): string {
    $attribute = $priority === null ? '' : "#[GateHook(priority: $priority)]";
    $implementsClause = $implements ? ' implements GateHookInterface' : '';

    return <<<PHP
<?php

declare(strict_types=1);

namespace $namespace;

use Marko\Authorization\Attributes\GateHook;
use Marko\Authorization\AuthorizableInterface;
use Marko\Authorization\Contracts\GateHookInterface;

$attribute
class $class$implementsClause
{
    public function before(?AuthorizableInterface \$user, string \$ability, array \$arguments): ?bool
    {
        return null;
    }
}
PHP;
}

describe('GateHook attribute', function (): void {
    it('defaults to priority 0 and targets classes', function (): void {
        $attribute = new ReflectionClass(GateHook::class)->getAttributes(Attribute::class)[0]->newInstance();

        expect(new GateHook()->priority)->toBe(0)
            ->and(new GateHook(priority: 50)->priority)->toBe(50)
            ->and($attribute->flags)->toBe(Attribute::TARGET_CLASS);
    });
});

describe('GateHookDiscovery', function (): void {
    it('discovers hooks across modules, highest priority first, keeping module order for ties', function (): void {
        $first = gateHookTestModule('test/first', [
            'LowHook.php' => gateHookSource('GateHookDiscoveryOrder', 'LowHook', -5),
            'TieHookA.php' => gateHookSource('GateHookDiscoveryOrder', 'TieHookA', 10),
            'NotAHookAtAll.php' => gateHookSource('GateHookDiscoveryOrder', 'NotAHookAtAll', null),
        ]);
        $second = gateHookTestModule('test/second', [
            'HighHook.php' => gateHookSource('GateHookDiscoveryOrder', 'HighHook', 100),
            'TieHookB.php' => gateHookSource('GateHookDiscoveryOrder', 'TieHookB', 10),
        ]);

        try {
            $definitions = new GateHookDiscovery()->discoverAll([$first, $second]);
        } finally {
            removeGateHookTestModule($first);
            removeGateHookTestModule($second);
        }

        expect(array_map(fn (GateHookDefinition $d): array => [$d->className, $d->priority], $definitions))
            ->toBe([
                ['GateHookDiscoveryOrder\\HighHook', 100],
                ['GateHookDiscoveryOrder\\TieHookA', 10],
                ['GateHookDiscoveryOrder\\TieHookB', 10],
                ['GateHookDiscoveryOrder\\LowHook', -5],
            ]);
    });

    it('returns nothing for a module without a src directory', function (): void {
        $module = new ModuleManifest(
            name: 'test/empty',
            version: '1.0.0',
            path: sys_get_temp_dir() . '/marko-gate-hook-missing',
        );

        expect(new GateHookDiscovery()->discoverAll([$module]))->toBe([]);
    });

    it('throws when a #[GateHook] class does not implement GateHookInterface', function (): void {
        $module = gateHookTestModule('test/broken', [
            'BrokenHook.php' => gateHookSource('GateHookDiscoveryBroken', 'BrokenHook', 0, implements: false),
        ]);

        try {
            expect(fn () => new GateHookDiscovery()->discoverAll([$module]))
                ->toThrow(
                    GateHookException::class,
                    "Gate hook 'GateHookDiscoveryBroken\\BrokenHook' does not implement GateHookInterface",
                );
        } finally {
            removeGateHookTestModule($module);
        }
    });
});

describe('GateHookCacheContributor', function (): void {
    it('compiles the hooks in run order and hydrates them back into equal definitions', function (): void {
        $module = gateHookTestModule('test/cache', [
            'CachedLow.php' => gateHookSource('GateHookCacheTest', 'CachedLow', 1),
            'CachedHigh.php' => gateHookSource('GateHookCacheTest', 'CachedHigh', 9),
        ]);
        $discovery = new GateHookDiscovery();
        $contributor = new GateHookCacheContributor($discovery);

        try {
            $live = $discovery->discoverAll([$module]);
            $section = $contributor->compile([$module]);
        } finally {
            removeGateHookTestModule($module);
        }

        expect($contributor->key())->toBe('gate_hooks')
            ->and($section)->toBe([
                ['className' => 'GateHookCacheTest\\CachedHigh', 'priority' => 9],
                ['className' => 'GateHookCacheTest\\CachedLow', 'priority' => 1],
            ])
            ->and($contributor->hydrate($section))->toEqual($live);
    });

    it(
        'throws a malformed section error for an invalid cached record',
        function (array $section, string $problem): void {
            $contributor = new GateHookCacheContributor(new GateHookDiscovery());

            expect(fn () => $contributor->hydrate($section))->toThrow(DiscoveryCacheException::class, $problem);
        },
    )->with([
        'record is not an array' => [['App\\Hook'], 'gate hook 0 must be an array'],
        'missing class name' => [[['priority' => 0]], 'gate hook 0.className must be a string'],
        'priority is not an int' => [[['className' => 'App\\Hook', 'priority' => '1']], 'gate hook 0.priority must be an int'],
    ]);
});
