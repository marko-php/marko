<?php

declare(strict_types=1);

use Marko\Core\Application;
use Marko\Core\Discovery\CachedDiscovery;
use Marko\Core\Discovery\DiscoveryCache;
use Marko\Core\Discovery\DiscoveryCompiler;
use Marko\Core\Exceptions\DiscoveryCacheException;
use Marko\Core\Module\ModuleManifest;

/**
 * A project with one vendor module and one app module (with a PSR-4 class).
 *
 * @return array{base: string, class: string}
 */
function cachedBootProject(): array
{
    $id = bin2hex(random_bytes(6));
    $base = sys_get_temp_dir() . "/marko-cached-boot-$id";
    $namespace = "CachedBoot$id";

    foreach (["$base/vendor/acme/core", "$base/app/blog/src"] as $dir) {
        mkdir($dir, 0755, true);
    }

    file_put_contents("$base/vendor/acme/core/composer.json", json_encode([
        'name' => 'acme/core',
        'extra' => ['marko' => ['module' => true]],
    ]));
    file_put_contents("$base/app/blog/composer.json", json_encode([
        'name' => 'app/blog',
        'require' => ['acme/core' => '*'],
        'autoload' => ['psr-4' => ["$namespace\\" => 'src/']],
        'extra' => ['marko' => ['module' => true]],
    ]));
    file_put_contents("$base/app/blog/module.php", "<?php\n\nreturn ['sequence' => ['after' => ['acme/core']]];\n");
    file_put_contents("$base/app/blog/src/Post.php", "<?php\n\nnamespace $namespace;\n\nclass Post {}\n");

    return ['base' => $base, 'class' => "$namespace\\Post"];
}

function cachedBootCleanup(
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

function cachedBootApplication(
    string $base,
): Application {
    return new Application(vendorPath: "$base/vendor", modulesPath: "$base/modules", appPath: "$base/app");
}

/**
 * Compile the cache from a live boot. marko/routing is autoloadable in this monorepo but not
 * installed as a module here, so its 'routes' and 'http_exception_responders' sections are added by hand.
 */
function cachedBootCompile(
    string $base,
): void {
    $live = cachedBootApplication($base);
    $live->initialize(false);
    $payload = new DiscoveryCompiler($live->container)->compile($live->modules);
    $payload['sections']['routes'] = [];
    $payload['sections']['http_exception_responders'] = [];
    $live->container->get(DiscoveryCache::class)->write($payload);
}

describe('Application cached boot', function (): void {
    beforeEach(function (): void {
        $this->savedEnv = [];

        foreach (['APP_ENV', 'MARKO_ENV', 'DISCOVERY_CACHE_ENABLED', 'DISCOVERY_CACHE_PATH'] as $key) {
            $this->savedEnv[$key] = [array_key_exists($key, $_ENV) ? $_ENV[$key] : null, getenv($key)];
            unset($_ENV[$key]);
            putenv($key);
        }

        $_ENV['APP_ENV'] = 'production';
        $this->project = cachedBootProject();
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

        cachedBootCleanup($this->project['base']);
    });

    it('boots the same modules in the same order from the cache as from live discovery', function (): void {
        cachedBootCompile($this->project['base']);
        $live = cachedBootApplication($this->project['base']);
        $live->initialize(false);

        // A vendor composer.json is never read on a cached boot.
        unlink($this->project['base'] . '/vendor/acme/core/composer.json');
        $cached = cachedBootApplication($this->project['base']);
        $cached->initialize();

        expect(array_map(fn (ModuleManifest $m): string => $m->name, $cached->modules))
            ->toBe(array_map(fn (ModuleManifest $m): string => $m->name, $live->modules));
    });

    it('throws a stale DiscoveryCacheException when an app composer.json changes after caching', function (): void {
        cachedBootCompile($this->project['base']);
        file_put_contents($this->project['base'] . '/app/blog/composer.json', '{"name": "app/blog"}');

        $app = cachedBootApplication($this->project['base']);

        expect(fn () => $app->initialize())
            ->toThrow(DiscoveryCacheException::class, 'is stale');

        // A failed boot leaves the bootstrap error handler installed for the uncaught exception
        $app->bootstrapErrorHandler->unregister();
    });

    it('rebuilds modules from the cache with their live module.php', function (): void {
        cachedBootCompile($this->project['base']);

        $app = cachedBootApplication($this->project['base']);
        $app->initialize();

        expect(array_map(fn (ModuleManifest $m): array => [$m->name, $m->source, $m->after], $app->modules))
            ->toBe([['acme/core', 'vendor', []], ['app/blog', 'app', ['acme/core']]])
            ->and($app->modules[1]->path)->toBe($this->project['base'] . '/app/blog');
    });

    it('registers PSR-4 autoloaders for app and modules modules from the cache', function (): void {
        cachedBootCompile($this->project['base']);

        // Added after compiling, so only the cached boot's autoloader can find it.
        $class = $this->project['class'] . 'Draft';
        $short = substr($class, strrpos($class, '\\') + 1);
        $namespace = substr($class, 0, strrpos($class, '\\'));
        file_put_contents(
            $this->project['base'] . "/app/blog/src/$short.php",
            "<?php\n\nnamespace $namespace;\n\nclass $short {}\n",
        );

        $app = cachedBootApplication($this->project['base']);
        $app->initialize();

        expect(class_exists($class))->toBeTrue();
    });

    it('binds CachedDiscovery with the cached sections, and an uncached one on a live boot', function (): void {
        cachedBootCompile($this->project['base']);

        $cached = cachedBootApplication($this->project['base']);
        $cached->initialize();
        $live = cachedBootApplication($this->project['base']);
        $live->initialize(false);

        expect($cached->container->get(CachedDiscovery::class)->isCached())->toBeTrue()
            ->and($cached->container->get(CachedDiscovery::class)->section('routes'))->toBe([])
            ->and($live->container->get(CachedDiscovery::class)->isCached())->toBeFalse();
    });

    it("throws stale when a cached module's module.php now disables it", function (): void {
        cachedBootCompile($this->project['base']);
        file_put_contents(
            $this->project['base'] . '/app/blog/module.php',
            "<?php\n\nreturn ['enabled' => false, 'sequence' => ['after' => ['acme/core']]];\n",
        );

        $app = cachedBootApplication($this->project['base']);

        expect(fn () => $app->initialize())
            ->toThrow(DiscoveryCacheException::class, "module.php of 'app/blog' changed");

        // A failed boot leaves the bootstrap error handler installed for the uncaught exception
        $app->bootstrapErrorHandler->unregister();
    });

    it("throws stale when a cached module's sequence changed in module.php", function (): void {
        cachedBootCompile($this->project['base']);
        file_put_contents($this->project['base'] . '/app/blog/module.php', "<?php\n\nreturn [];\n");

        $app = cachedBootApplication($this->project['base']);

        expect(fn () => $app->initialize())
            ->toThrow(DiscoveryCacheException::class, "module.php of 'app/blog' changed");

        // A failed boot leaves the bootstrap error handler installed for the uncaught exception
        $app->bootstrapErrorHandler->unregister();
    });

    it('ignores the cache when initialize is called with useDiscoveryCache false', function (): void {
        cachedBootCompile($this->project['base']);
        file_put_contents($this->project['base'] . '/app/blog/module.php', "<?php\n\nreturn [];\n");

        $app = cachedBootApplication($this->project['base']);
        $app->initialize(false);

        expect($app->container->get(CachedDiscovery::class)->isCached())->toBeFalse()
            ->and($app->modules)->toHaveCount(2);
    });

    it('throws a stale DiscoveryCacheException on boot when installed.json changes after caching', function (): void {
        mkdir($this->project['base'] . '/vendor/composer');
        file_put_contents($this->project['base'] . '/vendor/composer/installed.json', '{"packages": []}');
        cachedBootCompile($this->project['base']);

        file_put_contents(
            $this->project['base'] . '/vendor/composer/installed.json',
            '{"packages": [{"name": "acme/new"}]}',
        );

        $app = cachedBootApplication($this->project['base']);

        expect(fn () => $app->initialize())
            ->toThrow(DiscoveryCacheException::class, 'is stale');

        // A failed boot leaves the bootstrap error handler installed for the uncaught exception
        $app->bootstrapErrorHandler->unregister();
    });
});
