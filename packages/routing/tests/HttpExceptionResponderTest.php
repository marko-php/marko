<?php

declare(strict_types=1);

namespace Marko\Routing\Tests\HttpExceptionResponders;

use Attribute;
use Marko\Core\Container\Container;
use Marko\Core\Container\PreferenceRegistry;
use Marko\Core\Discovery\CachedDiscovery;
use Marko\Core\Discovery\ClassFileParser;
use Marko\Core\Exceptions\DiscoveryCacheException;
use Marko\Core\Exceptions\HttpExceptionInterface;
use Marko\Core\Module\ModuleManifest;
use Marko\Routing\Attributes\HttpExceptionResponder;
use Marko\Routing\Discovery\HttpExceptionResponderCacheContributor;
use Marko\Routing\Discovery\HttpExceptionResponderDefinition;
use Marko\Routing\Discovery\HttpExceptionResponderDiscovery;
use Marko\Routing\Exceptions\HttpException;
use Marko\Routing\Exceptions\HttpExceptionResponderException;
use Marko\Routing\Http\ExceptionRenderer;
use Marko\Routing\Http\HttpExceptionResponderInterface;
use Marko\Routing\Http\HttpExceptionResponderRegistry;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;
use Marko\Routing\RouteCollection;
use Marko\Routing\RouteDefinition;
use Marko\Routing\Router;
use Marko\Routing\RoutingBootstrapper;
use ReflectionClass;

class TeapotResponder implements HttpExceptionResponderInterface
{
    public function respond(
        HttpExceptionInterface $exception,
        Request $request,
    ): ?Response {
        return $exception->getStatusCode() === 404 ? new Response('teapot', 418) : null;
    }
}

class PassingResponder implements HttpExceptionResponderInterface
{
    public static int $calls = 0;

    public function respond(
        HttpExceptionInterface $exception,
        Request $request,
    ): ?Response {
        self::$calls++;

        return null;
    }
}

class GoneResponder implements HttpExceptionResponderInterface
{
    public function respond(
        HttpExceptionInterface $exception,
        Request $request,
    ): ?Response {
        return new Response('gone', 410);
    }
}

class MissingPostController
{
    public function show(): Response
    {
        throw HttpException::notFound('Post not found.');
    }

    public function forbidden(): Response
    {
        throw HttpException::forbidden();
    }
}

class OuterHeaderMiddleware implements MiddlewareInterface
{
    public function handle(
        Request $request,
        callable $next,
    ): Response {
        return $next($request)->withHeader('X-Outer', 'decorated');
    }
}

/**
 * A temporary module whose src/ holds the given files (name => PHP source).
 *
 * @param array<string, string> $files
 */
function responderTestModule(
    string $name,
    array $files,
): ModuleManifest {
    $path = sys_get_temp_dir() . '/marko-responder-test-' . bin2hex(random_bytes(8));
    mkdir($path . '/src', 0755, true);

    foreach ($files as $file => $source) {
        file_put_contents("$path/src/$file", $source);
    }

    return new ModuleManifest(name: $name, version: '1.0.0', path: $path);
}

function removeResponderTestModule(
    ModuleManifest $module,
): void {
    array_map('unlink', glob($module->path . '/src/*.php') ?: []);
    rmdir($module->path . '/src');
    rmdir($module->path);
}

function responderSource(
    string $namespace,
    string $class,
    ?int $priority,
    bool $implements = true,
    string $body = 'new Response(\'handled\', 299)',
): string {
    $attribute = $priority === null ? '' : "#[HttpExceptionResponder(priority: $priority)]";
    $implementsClause = $implements ? ' implements HttpExceptionResponderInterface' : '';

    return <<<PHP
<?php

declare(strict_types=1);

namespace $namespace;

use Marko\Core\Exceptions\HttpExceptionInterface;
use Marko\Routing\Attributes\HttpExceptionResponder;
use Marko\Routing\Http\HttpExceptionResponderInterface;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

$attribute
class $class$implementsClause
{
    public function respond(HttpExceptionInterface \$exception, Request \$request): ?Response
    {
        return $body;
    }
}
PHP;
}

function responderRequest(
    array $server = [],
): Request {
    return new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/posts/1', ...$server]);
}

/**
 * @param array<int, HttpExceptionResponderDefinition> $definitions
 */
function responderRegistry(
    array $definitions,
): HttpExceptionResponderRegistry {
    return new HttpExceptionResponderRegistry(new Container(new PreferenceRegistry()), $definitions);
}

/**
 * @param array<ModuleManifest> $modules
 */
function responderRouter(
    string $action,
    array $modules,
    ?CachedDiscovery $cachedDiscovery = null,
): Router {
    $preferenceRegistry = new PreferenceRegistry();
    $container = new Container($preferenceRegistry);

    if ($cachedDiscovery !== null) {
        $container->instance(CachedDiscovery::class, $cachedDiscovery);
    }

    $router = new RoutingBootstrapper(
        modules: $modules,
        container: $container,
        preferenceRegistry: $preferenceRegistry,
        classFileParser: new ClassFileParser(),
    )->boot([OuterHeaderMiddleware::class]);

    $container->get(RouteCollection::class)->add(new RouteDefinition(
        method: 'GET',
        path: '/posts/1',
        controller: MissingPostController::class,
        action: $action,
    ));

    return $router;
}

describe('HttpExceptionResponder attribute', function (): void {
    it('defaults to priority 0 and targets classes', function (): void {
        $attribute = new ReflectionClass(HttpExceptionResponder::class)
            ->getAttributes(Attribute::class)[0]
            ->newInstance();

        expect(new HttpExceptionResponder()->priority)->toBe(0)
            ->and(new HttpExceptionResponder(priority: 50)->priority)->toBe(50)
            ->and($attribute->flags)->toBe(Attribute::TARGET_CLASS);
    });
});

describe('HttpExceptionResponderDiscovery', function (): void {
    it('discovers attributed responders across modules, highest priority first', function (): void {
        $first = responderTestModule('acme/first', [
            'Low.php' => responderSource('Acme\\RespFirst', 'Low', 0),
            'High.php' => responderSource('Acme\\RespFirst', 'High', 100),
            'Plain.php' => responderSource('Acme\\RespFirst', 'Plain', null),
        ]);
        $second = responderTestModule('acme/second', [
            'Mid.php' => responderSource('Acme\\RespSecond', 'Mid', 50),
            'AlsoLow.php' => responderSource('Acme\\RespSecond', 'AlsoLow', 0),
        ]);

        $definitions = new HttpExceptionResponderDiscovery()->discoverAll([$first, $second]);

        expect(array_map(fn (HttpExceptionResponderDefinition $d): string => $d->className, $definitions))
            ->toBe(
                ['Acme\\RespFirst\\High', 'Acme\\RespSecond\\Mid', 'Acme\\RespFirst\\Low', 'Acme\\RespSecond\\AlsoLow'],
            )
            ->and(array_map(fn (HttpExceptionResponderDefinition $d): int => $d->priority, $definitions))
            ->toBe([100, 50, 0, 0]);

        removeResponderTestModule($first);
        removeResponderTestModule($second);
    });

    it('returns nothing for a module without a src directory', function (): void {
        $module = new ModuleManifest(
            name: 'acme/empty',
            version: '1.0.0',
            path: sys_get_temp_dir() . '/no-such-' . bin2hex(random_bytes(4)),
        );

        expect(new HttpExceptionResponderDiscovery()->discoverInModule($module))->toBe([]);
    });

    it('throws when an attributed class does not implement HttpExceptionResponderInterface', function (): void {
        $module = responderTestModule('acme/broken', [
            'NotAResponder.php' => responderSource('Acme\\RespBroken', 'NotAResponder', 0, implements: false),
        ]);

        try {
            new HttpExceptionResponderDiscovery()->discoverAll([$module]);
        } finally {
            removeResponderTestModule($module);
        }
    })->throws(
        HttpExceptionResponderException::class,
        "HTTP exception responder 'Acme\\RespBroken\\NotAResponder' does not implement HttpExceptionResponderInterface",
    );
});

describe('HttpExceptionResponderCacheContributor', function (): void {
    it('compiles the discovered responders in order and hydrates them back', function (): void {
        $module = responderTestModule('acme/cached', [
            'Low.php' => responderSource('Acme\\RespCached', 'Low', -10),
            'High.php' => responderSource('Acme\\RespCached', 'High', 10),
        ]);
        $contributor = new HttpExceptionResponderCacheContributor(new HttpExceptionResponderDiscovery());

        $section = $contributor->compile([$module]);
        $hydrated = $contributor->hydrate($section);
        removeResponderTestModule($module);

        expect($contributor->key())->toBe('http_exception_responders')
            ->and($section)->toBe([
                ['className' => 'Acme\\RespCached\\High', 'priority' => 10],
                ['className' => 'Acme\\RespCached\\Low', 'priority' => -10],
            ])
            ->and($hydrated[0]->className)->toBe('Acme\\RespCached\\High')
            ->and($hydrated[1]->priority)->toBe(-10);
    });

    it('rejects a malformed cached section', function (array $section, string $message): void {
        $contributor = new HttpExceptionResponderCacheContributor(new HttpExceptionResponderDiscovery());

        expect(fn () => $contributor->hydrate($section))->toThrow(DiscoveryCacheException::class, $message);
    })->with([
        'record not an array' => [['nope'], 'responder 0 must be an array'],
        'className missing' => [[['priority' => 0]], 'responder 0.className must be a string'],
        'priority not int' => [[['className' => 'A', 'priority' => '1']], 'responder 0.priority must be an int'],
    ]);
});

describe('HttpExceptionResponderRegistry', function (): void {
    it('returns the first non-null response in priority order', function (): void {
        PassingResponder::$calls = 0;
        $registry = responderRegistry([
            new HttpExceptionResponderDefinition(PassingResponder::class, 20),
            new HttpExceptionResponderDefinition(TeapotResponder::class, 10),
            new HttpExceptionResponderDefinition(GoneResponder::class, 0),
        ]);

        $response = $registry->respond(HttpException::notFound(), responderRequest());

        expect($response?->statusCode())->toBe(418)
            ->and($response?->body())->toBe('teapot')
            ->and(PassingResponder::$calls)->toBe(1);
    });

    it('returns null when every responder passes', function (): void {
        $registry = responderRegistry([new HttpExceptionResponderDefinition(PassingResponder::class, 0)]);

        expect($registry->respond(HttpException::notFound(), responderRequest()))->toBeNull();
    });

    it('builds each responder once through the container', function (): void {
        $registry = responderRegistry([new HttpExceptionResponderDefinition(TeapotResponder::class, 0)]);

        expect($registry->responders())->toHaveCount(1)
            ->and($registry->responders()[0])->toBe($registry->responders()[0])
            ->and($registry->responders()[0])->toBeInstanceOf(TeapotResponder::class);
    });
});

describe('ExceptionRenderer with responders', function (): void {
    it('uses a responder response instead of rendering', function (): void {
        $renderer = new ExceptionRenderer(responderRegistry([
            new HttpExceptionResponderDefinition(TeapotResponder::class, 0),
        ]));

        $response = $renderer->render(HttpException::notFound(), responderRequest());

        expect($response->statusCode())->toBe(418)
            ->and($response->body())->toBe('teapot');
    });

    it('renders as before when every responder passes', function (): void {
        $renderer = new ExceptionRenderer(responderRegistry([
            new HttpExceptionResponderDefinition(TeapotResponder::class, 0),
        ]));

        $response = $renderer->render(
            HttpException::forbidden(),
            responderRequest(['HTTP_ACCEPT' => 'application/json']),
        );

        expect($response->statusCode())->toBe(403)
            ->and(json_decode($response->body(), true))->toBe(['message' => 'Forbidden']);
    });

    it('renders as before without a registry', function (): void {
        $response = new ExceptionRenderer()->render(HttpException::notFound(), responderRequest());

        expect($response->statusCode())->toBe(404);
    });
});

describe('responders through Router::handle()', function (): void {
    it(
        'lets a discovered responder answer a controller exception, then outer middleware decorates it',
        function (): void {
            $module = responderTestModule('acme/router', [
                'RedirectingResponder.php' => responderSource(
                    'Acme\\RespRouter',
                    'RedirectingResponder',
                    0,
                    body: '$exception->getStatusCode() === 404 ? Response::redirect(\'/posts\', 303) : null',
                ),
            ]);

            $router = responderRouter('show', [$module]);
            $redirected = $router->handle(responderRequest());
            removeResponderTestModule($module);

            expect($redirected->statusCode())->toBe(303)
                ->and($redirected->headers()['Location'])->toBe('/posts')
                ->and($redirected->headers()['X-Outer'])->toBe('decorated');
        },
    );

    it('falls back to the default rendering when the responder passes', function (): void {
        $module = responderTestModule('acme/router-pass', [
            'OnlyNotFound.php' => responderSource(
                'Acme\\RespRouterPass',
                'OnlyNotFound',
                0,
                body: '$exception->getStatusCode() === 404 ? new Response(\'nf\', 404) : null',
            ),
        ]);

        $response = responderRouter('forbidden', [$module])->handle(responderRequest());
        removeResponderTestModule($module);

        expect($response->statusCode())->toBe(403)
            ->and($response->headers()['X-Outer'])->toBe('decorated');
    });

    it('reads the responders from the discovery cache on a cached boot', function (): void {
        $router = responderRouter('show', [], new CachedDiscovery([
            'routes' => [],
            'http_exception_responders' => [['className' => TeapotResponder::class, 'priority' => 0]],
        ]));

        expect($router->handle(responderRequest())->statusCode())->toBe(418);
    });
});
