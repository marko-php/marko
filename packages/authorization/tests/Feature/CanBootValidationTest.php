<?php

declare(strict_types=1);

namespace Marko\Authorization\Tests\Feature;

use DateTimeImmutable;
use Marko\Authentication\AuthManager;
use Marko\Authentication\Exceptions\AuthException;
use Marko\Authorization\AuthorizableInterface;
use Marko\Authorization\Contracts\GateInterface;
use Marko\Authorization\Exceptions\AuthorizationConfigurationException;
use Marko\Core\Application;
use Marko\Routing\Http\Request;
use Marko\Testing\Fake\FakeGuard;
use RuntimeException;

class CanBootUser implements AuthorizableInterface
{
    public function getAuthIdentifier(): int
    {
        return 1;
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthPassword(): string
    {
        return 'hashed';
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken(
        ?string $token,
    ): void {}

    public function getRememberTokenExpiresAt(): ?DateTimeImmutable
    {
        return null;
    }

    public function setRememberTokenExpiresAt(
        ?DateTimeImmutable $expiresAt,
    ): void {}

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }

    public function can(
        string $ability,
        mixed ...$arguments,
    ): bool {
        return false;
    }
}

/**
 * Build a project whose vendor/marko/* link to this monorepo, with one app
 * module (app/shop) in a namespace unique to this test.
 *
 * The app module binds the session and user provider the session guard needs,
 * unless a file named `broken-auth` exists in the project root: then binding
 * the user provider throws, which makes the guard impossible to build.
 *
 * `defaultGuard` sets authentication.default.guard, overriding the guard the
 * other options pick, for example to a name missing from authentication.guards.
 *
 * @param array{can?: bool, excluded?: bool, token?: bool, customDriver?: bool, defaultGuard?: string} $options
 * @return array{base: string, namespace: string}
 */
function canBootProject(
    array $options = [],
): array {
    $root = dirname(__DIR__, 4);
    $id = bin2hex(random_bytes(6));
    $namespace = "CanBoot$id";
    $base = sys_get_temp_dir() . "/marko-can-boot-$id";
    $src = "$base/app/shop/src";

    mkdir("$base/vendor/marko", 0755, true);
    mkdir("$base/config", 0755, true);
    mkdir($src, 0755, true);

    $packages = ['core', 'routing', 'config', 'clock', 'session', 'authentication', 'authorization'];

    if ($options['token'] ?? false) {
        $packages[] = 'authentication-token';
    }

    foreach ($packages as $package) {
        symlink("$root/packages/$package", "$base/vendor/marko/$package");
    }

    file_put_contents("$base/app/shop/composer.json", json_encode([
        'name' => 'app/shop',
        // Depending on marko/authorization makes app/shop boot after it.
        'require' => ['marko/authorization' => '*'],
        'autoload' => ['psr-4' => ["$namespace\\" => 'src/']],
        'extra' => ['marko' => ['module' => true]],
    ]));

    $boot = '';
    $guard = 'session';

    if ($options['token'] ?? false) {
        $guard = 'token';
    }

    if ($options['customDriver'] ?? false) {
        $guard = 'custom';
        // Registered from this app module's own boot callback; app/shop is not sequenced before marko/authorization.
        $boot = <<<'PHP'
                'boot' => function (\Marko\Authentication\Guard\GuardDriverRegistry $guardDriverRegistry): void {
                    $guardDriverRegistry->extend(
                        'custom',
                        fn (string $name): \Marko\Authentication\Contracts\GuardInterface => new \Marko\Testing\Fake\FakeGuard(name: $name),
                    );
                },
            PHP;
    }

    $guard = $options['defaultGuard'] ?? $guard;

    $brokenFlag = var_export("$base/broken-auth", true);
    file_put_contents("$base/app/shop/module.php", <<<PHP
        <?php

        declare(strict_types=1);

        return [
            'bindings' => [
                \\Marko\\Session\\Contracts\\SessionInterface::class => \\Marko\\Testing\\Fake\\FakeSession::class,
                \\Marko\\Authentication\\Contracts\\UserProviderInterface::class => function (): \\Marko\\Authentication\\Contracts\\UserProviderInterface {
                    if (file_exists($brokenFlag)) {
                        throw new \\RuntimeException('No user provider configured');
                    }

                    return new \\Marko\\Testing\\Fake\\FakeUserProvider();
                },
                \\Marko\\AuthenticationToken\\Contracts\\TokenRepositoryInterface::class => \\Marko\\AuthenticationToken\\Tests\\Fixtures\\InMemoryTokenRepository::class,
            ],
            'singletons' => [
                \\Marko\\Session\\Contracts\\SessionInterface::class,
            ],
        $boot
        ];
        PHP);

    file_put_contents("$base/config/authentication.php", <<<PHP
        <?php

        return [
            'default' => ['guard' => '$guard', 'provider' => 'users'],
            'guards' => [
                'session' => ['driver' => 'session', 'provider' => 'users'],
                'token' => ['driver' => 'token', 'provider' => 'users'],
                'custom' => ['driver' => 'custom', 'provider' => 'users'],
            ],
        ];
        PHP);

    $header = "<?php\n\ndeclare(strict_types=1);\n\nnamespace $namespace;\n\n"
        . "use Marko\\Authorization\\Attributes\\Can;\nuse Marko\\Authorization\\Middleware\\AuthorizationMiddleware;\n"
        . "use Marko\\Routing\\Attributes\\Get;\nuse Marko\\Routing\\Attributes\\WithoutMiddleware;\n"
        . "use Marko\\Routing\\Http\\Response;\n\n";

    file_put_contents("$src/HomeController.php", $header . <<<'PHP'
        class HomeController
        {
            #[Get('/')]
            public function index(): Response { return new Response('home'); }
        }
        PHP);

    if ($options['can'] ?? false) {
        $exclusion = $options['excluded'] ?? false ? '#[WithoutMiddleware(AuthorizationMiddleware::class)]' : '';
        file_put_contents("$src/AdminController.php", $header . <<<PHP
            class AdminController
            {
                #[Get('/admin')]
                #[Can('admin.access')]
                $exclusion
                public function dashboard(): Response { return new Response('admin'); }
            }
            PHP);
    }

    return ['base' => $base, 'namespace' => $namespace];
}

/**
 * Delete a project built by canBootProject(). Symlinks are unlinked, never followed.
 */
function canBootCleanup(
    string $path,
): void {
    if (
        is_link($path)
        || is_file($path)
    ) {
        unlink($path);

        return;
    }

    if (!is_dir($path)) {
        return;
    }

    foreach (scandir($path) ?: [] as $item) {
        if (
            $item !== '.'
            && $item !== '..'
        ) {
            canBootCleanup("$path/$item");
        }
    }

    rmdir($path);
}

function canBootApplication(
    string $base,
): Application {
    return new Application(vendorPath: "$base/vendor", modulesPath: "$base/modules", appPath: "$base/app");
}

/**
 * Run `marko discovery:cache` in a separate PHP process (booting live, as the
 * CLI does), so no controller class of the project is loaded into this one.
 *
 * @return array{exitCode: int, output: string}
 */
function canBootDiscoveryCache(
    string $base,
): array {
    $autoload = dirname(__DIR__, 4) . '/vendor/autoload.php';
    $script = 'require ' . var_export($autoload, true) . ';'
        . '$_ENV["APP_ENV"] = "production";'
        . '$app = new Marko\Core\Application(' . var_export("$base/vendor", true) . ', '
        . var_export("$base/modules", true) . ', ' . var_export("$base/app", true) . ');'
        . 'try { $app->initialize(false); } catch (Throwable $e) { echo get_class($e), ": ", $e->getMessage(); exit(1); }'
        . 'exit($app->commandRunner->run("discovery:cache", new Marko\Core\Command\Input(["marko", "discovery:cache"]), new Marko\Core\Command\Output(fopen("php://stdout", "w"))));';

    exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script) . ' 2>&1', $output, $exitCode);

    return ['exitCode' => $exitCode, 'output' => implode("\n", $output)];
}

function canBootRequest(
    string $uri,
): Request {
    return new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $uri]);
}

describe('#[Can] boot validation', function (): void {
    beforeEach(function (): void {
        $this->savedEnv = [];

        foreach (['APP_ENV', 'MARKO_ENV', 'DISCOVERY_CACHE_ENABLED', 'DISCOVERY_CACHE_PATH'] as $key) {
            $this->savedEnv[$key] = [array_key_exists($key, $_ENV) ? $_ENV[$key] : null, getenv($key)];
            unset($_ENV[$key]);
            putenv($key);
        }

        // Production, so a boot uses the discovery cache whenever one exists.
        $_ENV['APP_ENV'] = 'production';
        $this->projects = [];
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

        foreach ($this->projects as $project) {
            canBootCleanup($project['base']);
        }
    });

    it('fails a live boot when a Can route exists and the guard cannot be built', function (): void {
        $project = $this->projects[] = canBootProject(['can' => true]);
        touch($project['base'] . '/broken-auth');

        $app = canBootApplication($project['base']);

        try {
            $app->initialize();
            $this->fail('Expected AuthorizationConfigurationException');
        } catch (AuthorizationConfigurationException $e) {
            // A failed boot leaves the bootstrap error handler installed for the uncaught exception
            $app->bootstrapErrorHandler->unregister();
            expect($e->getMessage())->toContain("guard 'session'")
                ->toContain('No user provider configured')
                ->and($e->getContext())->toContain($project['namespace'] . '\\AdminController::dashboard');
        }
    });

    it('fails a live boot when a Can route exists and the default guard is not configured', function (): void {
        // 'sesion' is in neither the app's nor the package's authentication.guards.
        $project = $this->projects[] = canBootProject(['can' => true, 'defaultGuard' => 'sesion']);

        $app = canBootApplication($project['base']);

        try {
            $app->initialize();
            $this->fail('Expected AuthorizationConfigurationException');
        } catch (AuthorizationConfigurationException $e) {
            // A failed boot leaves the bootstrap error handler installed for the uncaught exception
            $app->bootstrapErrorHandler->unregister();
            expect($e->getMessage())->toContain("guard 'sesion'")
                ->toContain("Guard 'sesion' is not defined in authentication.guards")
                ->and($e->getPrevious())->toBeInstanceOf(AuthException::class)
                ->and($e->getContext())->toContain($project['namespace'] . '\\AdminController::dashboard');
        }
    });

    it('boots live without auth configuration when no route uses Can', function (): void {
        $project = $this->projects[] = canBootProject();
        touch($project['base'] . '/broken-auth');

        $app = canBootApplication($project['base']);
        $app->initialize();

        expect($app->router->handle(canBootRequest('/'))->body())->toBe('home');
    });

    it('does not check a Can route that excludes AuthorizationMiddleware', function (): void {
        $project = $this->projects[] = canBootProject(['can' => true, 'excluded' => true]);
        touch($project['base'] . '/broken-auth');

        $app = canBootApplication($project['base']);
        $app->initialize();

        expect($app->router->handle(canBootRequest('/admin'))->body())->toBe('admin');
    });

    it('boots live when the guard can be built', function (): void {
        $project = $this->projects[] = canBootProject(['can' => true]);

        $app = canBootApplication($project['base']);
        $app->initialize();

        expect($app->router->handle(canBootRequest('/admin'))->statusCode())->toBe(401);
    });

    it('fails discovery:cache on the same misconfiguration and writes no cache', function (): void {
        $project = $this->projects[] = canBootProject(['can' => true]);
        touch($project['base'] . '/broken-auth');

        $result = canBootDiscoveryCache($project['base']);

        expect($result['exitCode'])->not->toBe(0)
            ->and($result['output'])->toContain(AuthorizationConfigurationException::class)
            ->toContain("guard 'session'")
            ->and(glob($project['base'] . '/storage/cache/discovery*'))->toBe([]);
    });

    it('never reads routes or builds the guard on a cached boot', function (): void {
        $project = $this->projects[] = canBootProject(['can' => true]);
        $result = canBootDiscoveryCache($project['base']);
        expect($result['exitCode'])->toBe(0, $result['output']);

        // Building the guard now throws, so a cached boot that built it would fail.
        touch($project['base'] . '/broken-auth');

        $app = canBootApplication($project['base']);
        $app->initialize();

        // Reading #[Can] would have loaded the controller class.
        expect(class_exists($project['namespace'] . '\\AdminController', false))->toBeFalse()
            ->and($app->container->resolvedInstances(AuthManager::class))->toBe([])
            ->and(fn () => $app->router->handle(canBootRequest('/admin')))
            ->toThrow(RuntimeException::class, 'No user provider configured');
    });

    it('passes the boot check when the default guard is the token driver', function (): void {
        $project = $this->projects[] = canBootProject(['can' => true, 'token' => true]);

        $app = canBootApplication($project['base']);
        $app->initialize();

        $response = $app->router->handle(canBootRequest('/admin'));

        expect($response->statusCode())->toBe(401)
            ->and($app->container->get(AuthManager::class)->guard()->getName())->toBe('token');
    });

    it('passes the boot check for a custom guard driver registered in an app module boot callback', function (): void {
        $project = $this->projects[] = canBootProject(['can' => true, 'customDriver' => true]);

        $app = canBootApplication($project['base']);
        $app->initialize();

        $moduleNames = array_map(fn ($module): string => $module->name, $app->modules);

        // The driver is registered after marko/authorization boots, so only a post-boot check sees it.
        expect(array_search('app/shop', $moduleNames, true))
            ->toBeGreaterThan(array_search('marko/authorization', $moduleNames, true))
            ->and($app->container->get(AuthManager::class)->guard())->toBeInstanceOf(FakeGuard::class);
    });

    it('still authorizes a Can route for a user swapped in after a live boot', function (): void {
        $project = $this->projects[] = canBootProject(['can' => true]);

        $app = canBootApplication($project['base']);
        $app->initialize();

        // What the HTTP test client's actingAs() does: replace the guard after boot.
        $guard = new FakeGuard(name: 'session');
        $guard->setUser(new CanBootUser());
        $app->container->get(AuthManager::class)->useGuard('session', $guard);
        $app->container->get(GateInterface::class)->define(
            'admin.access',
            fn (?AuthorizableInterface $user): bool => $user !== null,
        );

        $response = $app->router->handle(canBootRequest('/admin'));

        expect($response->statusCode())->toBe(200)
            ->and($response->body())->toBe('admin');
    });
});
