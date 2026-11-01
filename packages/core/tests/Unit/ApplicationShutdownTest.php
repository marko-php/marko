<?php

declare(strict_types=1);

use Marko\Core\Application;
use Marko\Core\Environment\AppEnvironment;

/**
 * A project with two app modules: app/alpha loads first, app/omega after it.
 * Each records its boot and shutdown callbacks in $GLOBALS[$key].
 *
 * @return array{base: string, key: string}
 */
function applicationShutdownProject(): array
{
    $id = bin2hex(random_bytes(6));
    $base = sys_get_temp_dir() . "/marko-application-shutdown-$id";
    $key = "__marko_application_shutdown_$id";

    foreach (["$base/vendor", "$base/app/alpha", "$base/app/omega"] as $dir) {
        mkdir($dir, 0755, true);
    }

    foreach (['alpha' => [], 'omega' => ['after' => ['app/alpha']]] as $name => $sequence) {
        file_put_contents("$base/app/$name/composer.json", json_encode([
            'name' => "app/$name",
            'extra' => ['marko' => ['module' => true]],
        ]));
        $sequenceCode = var_export($sequence, true);
        file_put_contents("$base/app/$name/module.php", <<<PHP
            <?php

            use Marko\\Core\\Environment\\AppEnvironment;

            return [
                'sequence' => $sequenceCode,
                'boot' => function (): void { \$GLOBALS['$key'][] = '$name boot'; },
                'shutdown' => function (AppEnvironment \$appEnvironment): void {
                    \$GLOBALS['$key'][] = '$name shutdown (' . \$appEnvironment::class . ')';
                },
            ];
            PHP);
    }

    return ['base' => $base, 'key' => $key];
}

function applicationShutdownCleanup(
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

/**
 * The active exception handlers, bottom of the stack first, read without changing them.
 *
 * @return list<callable>
 */
function applicationShutdownExceptionHandlers(): array
{
    $handlers = [];

    while (($handler = set_exception_handler(null)) !== null) {
        restore_exception_handler();
        $handlers[] = $handler;
        restore_exception_handler();
    }

    restore_exception_handler();
    $handlers = array_reverse($handlers);

    foreach ($handlers as $handler) {
        set_exception_handler($handler);
    }

    return $handlers;
}

describe('Application::shutdown()', function (): void {
    beforeEach(function (): void {
        $this->savedEnv = [];

        foreach (['APP_ENV', 'MARKO_ENV', 'DISCOVERY_CACHE_ENABLED'] as $key) {
            $this->savedEnv[$key] = [array_key_exists($key, $_ENV) ? $_ENV[$key] : null, getenv($key)];
            unset($_ENV[$key]);
            putenv($key);
        }

        $_ENV['APP_ENV'] = 'production';
        $this->project = applicationShutdownProject();
        $base = $this->project['base'];
        $this->app = new Application(vendorPath: "$base/vendor", modulesPath: "$base/modules", appPath: "$base/app");
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

        unset($GLOBALS[$this->project['key']]);
        applicationShutdownCleanup($this->project['base']);
    });

    it('runs every module shutdown callback in reverse load order, with injected parameters', function (): void {
        $this->app->initialize(false);
        $this->app->shutdown();

        expect($GLOBALS[$this->project['key']])->toBe([
            'alpha boot',
            'omega boot',
            'omega shutdown (' . AppEnvironment::class . ')',
            'alpha shutdown (' . AppEnvironment::class . ')',
        ]);
    });

    it('runs the shutdown callbacks only once when called again', function (): void {
        $this->app->initialize(false);
        $this->app->shutdown();
        $this->app->shutdown();

        expect(array_filter(
            $GLOBALS[$this->project['key']],
            fn (string $entry): bool => str_contains($entry, 'shutdown'),
        ))->toHaveCount(2);
    });

    it('does nothing for an application that was never initialized', function (): void {
        $this->app->shutdown();

        expect($GLOBALS[$this->project['key']] ?? [])->toBe([]);
    });

    it('removes the bootstrap error handler when it is still installed', function (): void {
        $before = applicationShutdownExceptionHandlers();
        $this->app->bootstrapErrorHandler->register();

        $this->app->shutdown();

        expect(applicationShutdownExceptionHandlers())->toBe($before);
    });
});
