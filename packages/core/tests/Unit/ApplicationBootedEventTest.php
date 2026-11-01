<?php

declare(strict_types=1);

use Marko\Core\Application;
use Marko\Core\Discovery\DiscoveryCache;
use Marko\Core\Discovery\DiscoveryCompiler;
use Marko\Core\Event\ApplicationBooted;
use Marko\Core\Event\Event;

/**
 * A project with two app modules: app/auditor observes ApplicationBooted and
 * records what it sees, and app/zeta (which boots after it) sets a flag from
 * its boot callback.
 *
 * @return array{base: string, key: string}
 */
function applicationBootedProject(): array
{
    $id = bin2hex(random_bytes(6));
    $base = sys_get_temp_dir() . "/marko-application-booted-$id";
    $namespace = "ApplicationBooted$id";
    $key = "__marko_application_booted_$id";

    foreach (["$base/vendor", "$base/app/auditor/src", "$base/app/zeta"] as $dir) {
        mkdir($dir, 0755, true);
    }

    file_put_contents("$base/app/auditor/composer.json", json_encode([
        'name' => 'app/auditor',
        'autoload' => ['psr-4' => ["$namespace\\" => 'src/']],
        'extra' => ['marko' => ['module' => true]],
    ]));
    file_put_contents("$base/app/auditor/src/BootAuditor.php", <<<PHP
        <?php

        declare(strict_types=1);

        namespace $namespace;

        use Marko\\Core\\Attributes\\Observer;
        use Marko\\Core\\Event\\ApplicationBooted;

        #[Observer(event: ApplicationBooted::class)]
        class BootAuditor
        {
            public function handle(ApplicationBooted \$event): void
            {
                \$GLOBALS['$key'][] = 'observer saw zeta booted: ' . var_export(\$GLOBALS['$key.zeta'] ?? false, true);
            }
        }
        PHP);

    file_put_contents("$base/app/zeta/composer.json", json_encode([
        'name' => 'app/zeta',
        'extra' => ['marko' => ['module' => true]],
    ]));
    file_put_contents(
        "$base/app/zeta/module.php",
        "<?php\n\nreturn ['sequence' => ['after' => ['app/auditor']], 'boot' => function (): void { \$GLOBALS['$key.zeta'] = true; \$GLOBALS['$key'][] = 'zeta boot'; }];\n",
    );

    return ['base' => $base, 'key' => $key];
}

function applicationBootedCleanup(
    string $dir,
): void {
    if (!is_dir($dir)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($dir);
}

function applicationBootedApplication(
    string $base,
): Application {
    return new Application(vendorPath: "$base/vendor", modulesPath: "$base/modules", appPath: "$base/app");
}

describe('ApplicationBooted event', function (): void {
    beforeEach(function (): void {
        $this->savedEnv = [];

        foreach (['APP_ENV', 'MARKO_ENV', 'DISCOVERY_CACHE_ENABLED', 'DISCOVERY_CACHE_PATH'] as $key) {
            $this->savedEnv[$key] = [array_key_exists($key, $_ENV) ? $_ENV[$key] : null, getenv($key)];
            unset($_ENV[$key]);
            putenv($key);
        }

        $_ENV['APP_ENV'] = 'production';
        $this->project = applicationBootedProject();
    });

    afterEach(function (): void {
        foreach ($this->savedEnv as $key => [$env, $process]) {
            unset($_ENV[$key]);
            putenv($key);

            if ($env !== null) {
                $_ENV[$key] = $env;
            }

            if ($process !== false) {
                putenv("$key=$process");
            }
        }

        unset($GLOBALS[$this->project['key']], $GLOBALS[$this->project['key'] . '.zeta']);
        applicationBootedCleanup($this->project['base']);
    });

    it('is a plain core event', function (): void {
        expect(new ApplicationBooted())->toBeInstanceOf(Event::class);
    });

    it('dispatches ApplicationBooted after every module boot callback', function (): void {
        applicationBootedApplication($this->project['base'])->initialize(false);

        expect($GLOBALS[$this->project['key']])->toBe(['zeta boot', 'observer saw zeta booted: true']);
    });

    it('dispatches ApplicationBooted on a cached boot', function (): void {
        $live = applicationBootedApplication($this->project['base']);
        $live->initialize(false);
        $payload = new DiscoveryCompiler($live->container)->compile($live->modules);
        // marko/routing is autoloadable here but not installed as a module, so add its sections by hand.
        $payload['sections']['routes'] = [];
        $payload['sections']['http_exception_responders'] = [];
        $live->container->get(DiscoveryCache::class)->write($payload);
        unset($GLOBALS[$this->project['key']], $GLOBALS[$this->project['key'] . '.zeta']);

        $cached = applicationBootedApplication($this->project['base']);
        $cached->initialize();

        expect($cached->container->get(DiscoveryCache::class)->exists())->toBeTrue()
            ->and($GLOBALS[$this->project['key']])->toBe(['zeta boot', 'observer saw zeta booted: true']);
    });
});
