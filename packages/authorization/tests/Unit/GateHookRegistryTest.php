<?php

declare(strict_types=1);

use Marko\Authorization\AuthorizableInterface;
use Marko\Authorization\Contracts\GateHookInterface;
use Marko\Authorization\Discovery\GateHookCacheContributor;
use Marko\Authorization\Discovery\GateHookDiscovery;
use Marko\Authorization\GateHookRegistry;
use Marko\Core\Container\Container;
use Marko\Core\Discovery\CachedDiscovery;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepository;

class RegistryClock
{
    public function __construct(
        public readonly string $now = '2026-10-31',
    ) {}
}

class InjectedRegistryHook implements GateHookInterface
{
    public static int $constructed = 0;

    public function __construct(
        public readonly RegistryClock $clock,
    ) {
        self::$constructed++;
    }

    public function before(
        ?AuthorizableInterface $user,
        string $ability,
        array $arguments,
    ): ?bool {
        return null;
    }
}

class SecondRegistryHook implements GateHookInterface
{
    public function before(
        ?AuthorizableInterface $user,
        string $ability,
        array $arguments,
    ): ?bool {
        return null;
    }
}

function gateHookRegistry(
    CachedDiscovery $cachedDiscovery,
    ModuleManifest ...$modules,
): GateHookRegistry {
    $discovery = new GateHookDiscovery();

    return new GateHookRegistry(
        cachedDiscovery: $cachedDiscovery,
        gateHookCacheContributor: new GateHookCacheContributor($discovery),
        gateHookDiscovery: $discovery,
        moduleRepository: new ModuleRepository($modules),
        container: new Container(),
    );
}

describe('GateHookRegistry', function (): void {
    beforeEach(function (): void {
        InjectedRegistryHook::$constructed = 0;
    });

    it('builds the cached hooks through the container, in cached order, once', function (): void {
        $registry = gateHookRegistry(new CachedDiscovery([
            GateHookCacheContributor::KEY => [
                ['className' => SecondRegistryHook::class, 'priority' => 20],
                ['className' => InjectedRegistryHook::class, 'priority' => 10],
            ],
        ]));

        $hooks = $registry->hooks();

        expect($hooks)->toHaveCount(2)
            ->and($hooks[0])->toBeInstanceOf(SecondRegistryHook::class)
            ->and($hooks[1])->toBeInstanceOf(InjectedRegistryHook::class)
            ->and($hooks[1]->clock->now)->toBe('2026-10-31')
            ->and($registry->hooks())->toBe($hooks)
            ->and(InjectedRegistryHook::$constructed)->toBe(1);
    });

    it('scans the enabled modules when the boot did not use the cache', function (): void {
        $path = sys_get_temp_dir() . '/marko-gate-hook-registry-' . bin2hex(random_bytes(8));
        mkdir($path . '/src', 0755, true);
        file_put_contents($path . '/src/ScannedAdminHook.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace GateHookRegistryScan;

use Marko\Authorization\Attributes\GateHook;
use Marko\Authorization\AuthorizableInterface;
use Marko\Authorization\Contracts\GateHookInterface;

#[GateHook(priority: 100)]
class ScannedAdminHook implements GateHookInterface
{
    public function before(?AuthorizableInterface $user, string $ability, array $arguments): ?bool
    {
        return true;
    }
}
PHP);
        $module = new ModuleManifest(name: 'test/scan', version: '1.0.0', path: $path);

        try {
            $hooks = gateHookRegistry(new CachedDiscovery(), $module)->hooks();
        } finally {
            unlink($path . '/src/ScannedAdminHook.php');
            rmdir($path . '/src');
            rmdir($path);
        }

        expect($hooks)->toHaveCount(1)
            ->and($hooks[0]::class)->toBe('GateHookRegistryScan\\ScannedAdminHook');
    });

    it('returns no hooks when none are declared', function (): void {
        expect(gateHookRegistry(new CachedDiscovery())->hooks())->toBe([]);
    });
});
