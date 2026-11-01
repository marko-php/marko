<?php

declare(strict_types=1);

use Marko\Core\Application;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Core\Container\PreferenceRegistry;
use Marko\Core\Discovery\ClassFileParser;
use Marko\Core\Discovery\DiscoveryCache;
use Marko\Core\Discovery\DiscoveryCompiler;
use Marko\Core\Exceptions\DiscoveryCacheException;
use Marko\Core\Module\ManifestParser;
use Marko\Core\Module\ModuleDiscovery;
use Marko\Core\Module\ModuleManifest;
use Marko\Routing\Discovery\HttpExceptionResponderCacheContributor;
use Marko\Routing\Exceptions\RouteException;
use Marko\Routing\Http\Request;
use Marko\Routing\RouteCacheContributor;
use Marko\Routing\RouteCollection;
use Marko\Routing\RouteCollector;
use Marko\Routing\RouteDefinition;

/**
 * Records every call a boot makes into module discovery, composer.json
 * parsing and class-file scanning.
 */
class RouteCacheRecordingManifestParser extends ManifestParser
{
    public int $parseCalls = 0;

    public function parse(
        string $modulePath,
    ): ModuleManifest {
        $this->parseCalls++;

        return parent::parse($modulePath);
    }
}

readonly class RouteCacheRecordingModuleDiscovery extends ModuleDiscovery
{
    public function __construct(
        private ArrayObject $calls,
        ManifestParser $parser,
    ) {
        parent::__construct($parser);
    }

    public function discoverInVendor(
        string $vendorDir,
    ): array {
        $this->calls[] = 'vendor';

        return parent::discoverInVendor($vendorDir);
    }

    public function discoverInModules(
        string $modulesDir,
    ): array {
        $this->calls[] = 'modules';

        return parent::discoverInModules($modulesDir);
    }

    public function discoverInApp(
        string $appDir,
    ): array {
        $this->calls[] = 'app';

        return parent::discoverInApp($appDir);
    }
}

class RouteCacheRecordingClassFileParser extends ClassFileParser
{
    public int $calls = 0;

    public function extractClassName(
        string $filePath,
    ): ?string {
        $this->calls++;

        return parent::extractClassName($filePath);
    }

    public function loadClass(
        string $filePath,
        string $className,
    ): bool {
        $this->calls++;

        return parent::loadClass($filePath, $className);
    }

    public function findPhpFiles(
        string $directory,
    ): iterable {
        $this->calls++;

        return parent::findPhpFiles($directory);
    }
}

/**
 * Build a project whose vendor/marko/{core,routing} link to this monorepo and
 * whose app/shop module declares controllers in a namespace unique to this test.
 *
 * @return array{base: string, namespace: string}
 */
function routeCacheProject(): array
{
    $root = dirname(__DIR__, 3);
    $id = bin2hex(random_bytes(6));
    $namespace = "RouteCache$id";
    $base = sys_get_temp_dir() . "/marko-route-cache-$id";
    $src = "$base/app/shop/src";

    mkdir("$base/vendor/marko", 0755, true);
    mkdir($src, 0755, true);
    symlink("$root/packages/core", "$base/vendor/marko/core");
    symlink("$root/packages/routing", "$base/vendor/marko/routing");

    file_put_contents("$base/app/shop/composer.json", json_encode([
        'name' => 'app/shop',
        'autoload' => ['psr-4' => ["$namespace\\" => 'src/']],
        'extra' => ['marko' => ['module' => true]],
    ]));
    file_put_contents(
        "$base/app/shop/module.php",
        "<?php\n\nreturn ['globalMiddleware' => [\\$namespace\\GlobalHeader::class]];\n",
    );

    $header = "<?php\n\ndeclare(strict_types=1);\n\nnamespace $namespace;\n\n";
    $uses = "use Marko\\Routing\\Attributes\\Get;\nuse Marko\\Routing\\Attributes\\Post;\n"
        . "use Marko\\Routing\\Attributes\\RoutePrefix;\nuse Marko\\Routing\\Attributes\\WithoutMiddleware;\n"
        . "use Marko\\Routing\\Http\\Request;\nuse Marko\\Routing\\Http\\Response;\n"
        . "use Marko\\Routing\\Middleware\\MiddlewareInterface;\nuse Marko\\Core\\Attributes\\Preference;\n\n";

    file_put_contents("$src/GlobalHeader.php", $header . $uses . <<<'PHP'
        class GlobalHeader implements MiddlewareInterface
        {
            public function handle(
                Request $request,
                callable $next,
            ): Response {
                return $next($request);
            }
        }
        PHP);

    file_put_contents("$src/CatalogController.php", $header . $uses . <<<'PHP'
        #[RoutePrefix('/catalog', namePrefix: 'catalog.')]
        class CatalogController
        {
            #[Get('/', name: 'index')]
            public function index(): Response { return new Response('catalog'); }

            #[Get('/{id:\d+}', middleware: [GlobalHeader::class], name: 'show')]
            public function show(int $id): Response { return new Response("item $id"); }

            #[Get('/{slug}')]
            public function slug(string $slug): Response { return new Response("slug $slug"); }

            #[Get('/files/{path*}')]
            public function files(string $path): Response { return new Response("file $path"); }

            #[Post('/', name: 'store')]
            #[WithoutMiddleware(GlobalHeader::class)]
            public function store(): Response { return new Response('stored', 201); }
        }
        PHP);

    file_put_contents("$src/BaseAdminController.php", $header . $uses . <<<'PHP'
        class BaseAdminController
        {
            #[Get('/admin', name: 'admin')]
            public function dashboard(): Response { return new Response('base admin'); }
        }
        PHP);

    file_put_contents("$src/PreferredAdminController.php", $header . $uses . <<<'PHP'
        #[Preference(replaces: BaseAdminController::class)]
        class PreferredAdminController extends BaseAdminController {}
        PHP);

    file_put_contents("$src/UnusedService.php", $header . "class UnusedService {}\n");

    return ['base' => $base, 'namespace' => $namespace];
}

/**
 * Delete a project built by routeCacheProject(). Symlinks are unlinked, never followed.
 */
function routeCacheCleanup(
    string $path,
): void {
    if (is_link($path) || is_file($path)) {
        unlink($path);

        return;
    }

    if (!is_dir($path)) {
        return;
    }

    foreach (scandir($path) ?: [] as $item) {
        if ($item !== '.' && $item !== '..') {
            routeCacheCleanup("$path/$item");
        }
    }

    rmdir($path);
}

function routeCacheApplication(
    string $base,
    ?ManifestParser $manifestParser = null,
    ?ModuleDiscovery $moduleDiscovery = null,
    ?ClassFileParser $classFileParser = null,
): Application {
    $manifestParser ??= new ManifestParser();

    return new Application(
        vendorPath: "$base/vendor",
        modulesPath: "$base/modules",
        appPath: "$base/app",
        manifestParser: $manifestParser,
        moduleDiscovery: $moduleDiscovery ?? new ModuleDiscovery($manifestParser),
        classFileParser: $classFileParser ?? new ClassFileParser(),
    );
}

/**
 * Compile the cache with `marko discovery:cache` in a separate PHP process, so
 * no controller class of the project is ever loaded into this one.
 */
function routeCacheCompileInSubprocess(
    string $base,
): void {
    $autoload = dirname(__DIR__, 3) . '/vendor/autoload.php';
    $script = 'require ' . var_export($autoload, true) . ';'
        . '$app = new Marko\Core\Application(' . var_export("$base/vendor", true) . ', '
        . var_export("$base/modules", true) . ', ' . var_export("$base/app", true) . ');'
        . '$app->initialize(false);'
        . 'exit($app->commandRunner->run("discovery:cache", new Marko\Core\Command\Input(["marko", "discovery:cache"]), new Marko\Core\Command\Output(fopen("php://stdout", "w"))));';

    exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script) . ' 2>&1', $output, $exitCode);

    if ($exitCode !== 0) {
        throw new RuntimeException("discovery:cache failed:\n" . implode("\n", $output));
    }
}

/**
 * @return array<int, array<string, mixed>>
 */
function routeCacheDescribe(
    RouteCollection $routes,
): array {
    return array_map(fn (RouteDefinition $route): array => [
        'method' => $route->method,
        'path' => $route->path,
        'controller' => $route->controller,
        'action' => $route->action,
        'middleware' => $route->middleware,
        'name' => $route->name,
        'withoutMiddleware' => $route->withoutMiddleware,
        'constraints' => $route->constraints,
        'regex' => $route->regex,
    ], $routes->inMatchOrder());
}

function routeCacheRequest(
    string $method,
    string $uri,
): Request {
    return new Request(server: ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri]);
}

describe('route discovery cache', function (): void {
    beforeEach(function (): void {
        $this->savedEnv = [];

        foreach (['APP_ENV', 'MARKO_ENV', 'DISCOVERY_CACHE_ENABLED', 'DISCOVERY_CACHE_PATH'] as $key) {
            $this->savedEnv[$key] = [array_key_exists($key, $_ENV) ? $_ENV[$key] : null, getenv($key)];
            unset($_ENV[$key]);
            putenv($key);
        }

        $_ENV['APP_ENV'] = 'production';
        $this->project = routeCacheProject();
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

        routeCacheCleanup($this->project['base']);
    });

    it('declares the route and responder contributors in module.php', function (): void {
        $module = require dirname(__DIR__) . '/module.php';

        expect($module['discovery'])->toBe(
            [RouteCacheContributor::class, HttpExceptionResponderCacheContributor::class],
        );
    });

    it(
        'hydrates routes identical to live discovery in order, names, middleware, constraints and exclusions',
        function (): void {
            $live = routeCacheApplication($this->project['base']);
            $live->initialize(false);
            $liveRoutes = $live->container->get(RouteCollection::class);

            runDiscoveryCacheCommand($live);

            $cached = routeCacheApplication($this->project['base']);
            $cached->initialize();
            $cachedRoutes = $cached->container->get(RouteCollection::class);

            expect($cachedRoutes)->not->toBe($liveRoutes)
                ->and(routeCacheDescribe($cachedRoutes))->toBe(routeCacheDescribe($liveRoutes))
                ->and(array_map(fn (RouteDefinition $r): string => "$r->method $r->path", $cachedRoutes->all()))
                ->toBe(array_map(fn (RouteDefinition $r): string => "$r->method $r->path", $liveRoutes->all()))
                ->and($cachedRoutes->names())->toBe($liveRoutes->names())
                ->and(count($cachedRoutes->all()))->toBe(6);
        },
    );

    it('caches routes inherited through a Preference', function (): void {
        $namespace = $this->project['namespace'];
        $live = routeCacheApplication($this->project['base']);
        $live->initialize(false);
        runDiscoveryCacheCommand($live);

        $cached = routeCacheApplication($this->project['base']);
        $cached->initialize();
        $admin = $cached->container->get(RouteCollection::class)->named('admin');

        expect($admin?->controller)->toBe("$namespace\\PreferredAdminController")
            ->and($cached->router->handle(routeCacheRequest('GET', '/admin'))->body())->toBe('base admin');
    });

    it('does not load controllers that the request did not match', function (): void {
        $namespace = $this->project['namespace'];
        routeCacheCompileInSubprocess($this->project['base']);

        $app = routeCacheApplication($this->project['base']);
        $app->initialize();
        $response = $app->router->handle(routeCacheRequest('GET', '/catalog/7'));

        expect($response->body())->toBe('item 7')
            ->and(class_exists("$namespace\\CatalogController", false))->toBeTrue()
            ->and(class_exists("$namespace\\BaseAdminController", false))->toBeFalse()
            ->and(class_exists("$namespace\\PreferredAdminController", false))->toBeFalse()
            ->and(class_exists("$namespace\\UnusedService", false))->toBeFalse();
    });

    it(
        'boots from a warm cache without calling ModuleDiscovery, ManifestParser::parse or ClassFileParser',
        function (): void {
            routeCacheCompileInSubprocess($this->project['base']);

            $calls = new ArrayObject();
            $manifestParser = new RouteCacheRecordingManifestParser();
            $classFileParser = new RouteCacheRecordingClassFileParser();
            $app = routeCacheApplication(
                $this->project['base'],
                $manifestParser,
                new RouteCacheRecordingModuleDiscovery($calls, $manifestParser),
                $classFileParser,
            );
            $app->initialize();
            $notFound = $app->router->handle(routeCacheRequest('GET', '/nothing-here'));

            expect($notFound->statusCode())->toBe(404)
                ->and(iterator_to_array($calls))->toBe([])
                ->and($manifestParser->parseCalls)->toBe(0)
                ->and($classFileParser->calls)->toBe(0);

            $live = routeCacheApplication($this->project['base']);
            $live->initialize(false);
            $names = fn (Application $application): array => array_map(
                fn (ModuleManifest $m): string => $m->name,
                $application->modules,
            );

            expect($names($app))->toBe($names($live))
                ->and($names($app))->toHaveCount(3);
        },
    );

    it('throws stale when a cached module changed its global middleware in module.php', function (): void {
        $namespace = $this->project['namespace'];
        routeCacheCompileInSubprocess($this->project['base']);

        // A module.php edit that is not reflected in the cache makes it stale.
        file_put_contents("{$this->project['base']}/app/shop/module.php", "<?php\n\nreturn [];\n");
        $app = routeCacheApplication($this->project['base']);

        expect(fn () => $app->initialize())->toThrow(
            DiscoveryCacheException::class,
            "module.php of 'app/shop' changed",
        );

        // A failed boot leaves the bootstrap error handler installed for the uncaught exception
        $app->bootstrapErrorHandler->unregister();

        file_put_contents(
            "{$this->project['base']}/app/shop/module.php",
            "<?php\n\nreturn ['globalMiddleware' => [\\$namespace\\GlobalHeader::class]];\n",
        );
        $app = routeCacheApplication($this->project['base']);
        $app->initialize();

        expect($app->router->handle(routeCacheRequest('POST', '/catalog'))->statusCode())->toBe(201);
    });

    it('throws malformed when a cached route record is invalid', function (): void {
        $contributor = new RouteCacheContributor(new RouteCollector(new PreferenceRegistry(), new ClassFileParser()));

        expect(fn () => $contributor->hydrate([['method' => 'GET', 'path' => '/']]))
            ->toThrow(
                DiscoveryCacheException::class,
                "Discovery cache section 'routes' is malformed: route 0.controller must be a string",
            )
            ->and(fn () => $contributor->hydrate(['nope']))
            ->toThrow(DiscoveryCacheException::class, 'route 0 must be an array')
            ->and(fn () => $contributor->hydrate([[
                'method' => 'GET',
                'path' => '/',
                'controller' => 'A',
                'action' => 'b',
                'middleware' => [1],
                'name' => null,
                'withoutMiddleware' => [],
            ]]))
            ->toThrow(DiscoveryCacheException::class, 'route 0.middleware must be a list of strings');
    });

    it('still rejects an excluded middleware missing from the stack on a cached boot', function (): void {
        $contributor = new RouteCacheContributor(new RouteCollector(new PreferenceRegistry(), new ClassFileParser()));
        $routes = $contributor->hydrate([[
            'method' => 'GET',
            'path' => '/',
            'controller' => 'App\\HomeController',
            'action' => 'index',
            'middleware' => [],
            'name' => null,
            'withoutMiddleware' => ['App\\Missing'],
        ]]);

        $live = routeCacheApplication($this->project['base']);
        $live->initialize(false);
        $payload = new DiscoveryCompiler($live->container)->compile($live->modules);
        $payload['sections']['routes'] = array_map(fn (RouteDefinition $r): array => [
            'method' => $r->method,
            'path' => $r->path,
            'controller' => $r->controller,
            'action' => $r->action,
            'middleware' => $r->middleware,
            'name' => $r->name,
            'withoutMiddleware' => $r->withoutMiddleware,
        ], $routes->all());
        $live->container->get(DiscoveryCache::class)->write($payload);

        $cached = routeCacheApplication($this->project['base']);

        expect(fn () => $cached->initialize())->toThrow(RouteException::class);

        // A failed boot leaves the bootstrap error handler installed for the uncaught exception
        $cached->bootstrapErrorHandler->unregister();
    });
});

function runDiscoveryCacheCommand(
    Application $app,
): void {
    $stream = fopen('php://memory', 'w+');
    $exitCode = $app->commandRunner->run(
        'discovery:cache',
        new Input(['marko', 'discovery:cache']),
        new Output($stream),
    );
    rewind($stream);
    $output = (string) stream_get_contents($stream);
    fclose($stream);

    if ($exitCode !== 0) {
        throw new RuntimeException("discovery:cache failed:\n$output");
    }
}
