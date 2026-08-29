<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Tests\Worker;

use Marko\Core\Application;
use Marko\Roadrunner\Http\Psr7RequestBridge;
use Marko\Roadrunner\Http\Psr7ResponseBridge;

use function Marko\Roadrunner\Tests\inProcessHarnessFixturePath;
use function Marko\Roadrunner\Tests\inProcessHarnessPsr7Request;
use function Marko\Roadrunner\Tests\inProcessHarnessSessionId;

use Marko\Roadrunner\Worker\WorkerLogger;
use Marko\Roadrunner\Worker\WorkerRequestHandler;
use Marko\Routing\MatchedRoute;
use Marko\Routing\Router;

use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * Wires the per-request reset lifecycle into WorkerRequestHandler: every
 * already-resolved ResettableInterface instance must be cleared before
 * (not after) each request, generically discovered through
 * ContainerInterface::resolvedInstances() rather than a hardcoded list.
 */
describe('WorkerRequestHandler reset lifecycle', function (): void {
    it('resets both session and identity state across interleaved requests', function (): void {
        $app = Application::boot(inProcessHarnessFixturePath());
        $psr7Worker = new FakePsr7Worker([
            inProcessHarnessPsr7Request('GET', '/session/write', inProcessHarnessSessionId()),
            inProcessHarnessPsr7Request('GET', '/session/read', inProcessHarnessSessionId()),
            inProcessHarnessPsr7Request('GET', '/session/write', inProcessHarnessSessionId()),
        ]);
        $handler = new WorkerRequestHandler(
            psr7Worker: $psr7Worker,
            router: $app->router,
            requestBridge: new Psr7RequestBridge(),
            responseBridge: new Psr7ResponseBridge(),
            logger: new WorkerLogger(),
            container: $app->container,
        );

        $handler->run();

        $bodies = array_map(
            static fn (ResponseInterface $response): string => (string) $response->getBody(),
            $psr7Worker->responses,
        );

        // Interleave: authenticated -> anonymous -> a different session.
        // The anonymous request in the middle is where a stale cached
        // identity would do the most damage, and an A -> B sequence alone
        // would not catch it.
        expect($bodies[0])->toContain('visits=1')
            ->toContain('user=1')
            ->and($bodies[1])->toContain('visits=0')
            ->toContain('user=guest')
            ->and($bodies[2])->toContain('visits=1')
            ->toContain('user=1');
    });

    it('isolates session state between two sequential requests', function (): void {
        $app = Application::boot(inProcessHarnessFixturePath());
        $psr7Worker = new FakePsr7Worker([
            inProcessHarnessPsr7Request('GET', '/session/write', inProcessHarnessSessionId()),
            inProcessHarnessPsr7Request('GET', '/session/read', inProcessHarnessSessionId()),
            inProcessHarnessPsr7Request('GET', '/session/write', inProcessHarnessSessionId()),
        ]);
        $handler = new WorkerRequestHandler(
            psr7Worker: $psr7Worker,
            router: $app->router,
            requestBridge: new Psr7RequestBridge(),
            responseBridge: new Psr7ResponseBridge(),
            logger: new WorkerLogger(),
            container: $app->container,
        );

        $handler->run();

        $bodies = array_map(
            static fn (ResponseInterface $response): string => (string) $response->getBody(),
            $psr7Worker->responses,
        );

        expect($bodies[0])->toContain('visits=1')
            ->and($bodies[1])->toContain('visits=0')
            ->and($bodies[2])->toContain('visits=1');
    });

    it('isolates the authenticated user between two sequential requests', function (): void {
        $app = Application::boot(inProcessHarnessFixturePath());
        $psr7Worker = new FakePsr7Worker([
            inProcessHarnessPsr7Request('GET', '/session/write', inProcessHarnessSessionId()),
            inProcessHarnessPsr7Request('GET', '/session/read', inProcessHarnessSessionId()),
            inProcessHarnessPsr7Request('GET', '/session/write', inProcessHarnessSessionId()),
        ]);
        $handler = new WorkerRequestHandler(
            psr7Worker: $psr7Worker,
            router: $app->router,
            requestBridge: new Psr7RequestBridge(),
            responseBridge: new Psr7ResponseBridge(),
            logger: new WorkerLogger(),
            container: $app->container,
        );

        $handler->run();

        $bodies = array_map(
            static fn (ResponseInterface $response): string => (string) $response->getBody(),
            $psr7Worker->responses,
        );

        expect($bodies[0])->toContain('user=1')
            ->and($bodies[1])->toContain('user=guest')
            ->and($bodies[2])->toContain('user=1');
    });

    it('resets a resolved resettable service between requests', function (): void {
        $router = new Router(new FakeRouteMatcher(), new NullContainer());
        $resettable = new RecordingResettable();
        $container = new StubResettableContainer(['service' => $resettable]);
        $psr7Worker = new FakePsr7Worker([
            new ServerRequest('GET', 'https://example.test/one'),
            new ServerRequest('GET', 'https://example.test/two'),
        ]);
        $handler = new WorkerRequestHandler(
            psr7Worker: $psr7Worker,
            router: $router,
            requestBridge: new Psr7RequestBridge(),
            responseBridge: new Psr7ResponseBridge(),
            logger: new WorkerLogger(),
            container: $container,
        );

        $handler->run();

        expect($resettable->resetCount)->toBe(2);
    });

    it('skips a resettable service that the container never resolved', function (): void {
        $router = new Router(new FakeRouteMatcher(), new NullContainer());
        $container = new StubResettableContainer([]);
        $psr7Worker = new FakePsr7Worker([
            new ServerRequest('GET', 'https://example.test/one'),
        ]);
        $handler = new WorkerRequestHandler(
            psr7Worker: $psr7Worker,
            router: $router,
            requestBridge: new Psr7RequestBridge(),
            responseBridge: new Psr7ResponseBridge(),
            logger: new WorkerLogger(),
            container: $container,
        );

        $handler->run();

        expect($psr7Worker->responses)->toHaveCount(1)
            ->and($psr7Worker->responses[0]->getStatusCode())->toBe(404);
    });

    it('does not instantiate a service that the request never used', function (): void {
        $router = new Router(new FakeRouteMatcher(), new NullContainer());
        $resettable = new RecordingResettable();
        $container = new StubResettableContainer(['service' => $resettable]);
        $psr7Worker = new FakePsr7Worker([
            new ServerRequest('GET', 'https://example.test/one'),
        ]);
        $handler = new WorkerRequestHandler(
            psr7Worker: $psr7Worker,
            router: $router,
            requestBridge: new Psr7RequestBridge(),
            responseBridge: new Psr7ResponseBridge(),
            logger: new WorkerLogger(),
            container: $container,
        );

        $handler->run();

        expect($container->getCallCount)->toBe(0);
    });

    it('resets before the request rather than after', function (): void {
        /** @var list<string> $log */
        $log = [];
        $matcher = new FakeRouteMatcher(onMatch: function () use (&$log): null {
            $log[] = 'route';

            return null;
        });
        $router = new Router($matcher, new NullContainer());
        $resettable = new RecordingResettable(onReset: function () use (&$log): void {
            $log[] = 'reset';
        });
        $container = new StubResettableContainer(['service' => $resettable]);
        $psr7Worker = new FakePsr7Worker([
            new ServerRequest('GET', 'https://example.test/one'),
        ]);
        $handler = new WorkerRequestHandler(
            psr7Worker: $psr7Worker,
            router: $router,
            requestBridge: new Psr7RequestBridge(),
            responseBridge: new Psr7ResponseBridge(),
            logger: new WorkerLogger(),
            container: $container,
        );

        $handler->run();

        expect($log)->toBe(['reset', 'route']);
    });

    it('still resets after a request throws', function (): void {
        $matcher = new FakeRouteMatcher(onMatch: function (string $method, string $path): ?MatchedRoute {
            if ($path === '/boom') {
                throw new RuntimeException('request one exploded');
            }

            return null;
        });
        $router = new Router($matcher, new NullContainer());
        $resettable = new RecordingResettable();
        $container = new StubResettableContainer(['service' => $resettable]);
        $psr7Worker = new FakePsr7Worker([
            new ServerRequest('GET', 'https://example.test/boom'),
            new ServerRequest('GET', 'https://example.test/two'),
        ]);
        $handler = new WorkerRequestHandler(
            psr7Worker: $psr7Worker,
            router: $router,
            requestBridge: new Psr7RequestBridge(),
            responseBridge: new Psr7ResponseBridge(),
            logger: new WorkerLogger(),
            container: $container,
        );

        $handler->run();

        expect($resettable->resetCount)->toBe(2);
    });

    it('fails the request loudly when a reset cannot be performed', function (): void {
        $router = new Router(new FakeRouteMatcher(), new NullContainer());
        $container = new StubResettableContainer(['service' => new FaultyResettable()]);
        $psr7Worker = new FakePsr7Worker([
            new ServerRequest('GET', 'https://example.test/one'),
        ]);
        $handler = new WorkerRequestHandler(
            psr7Worker: $psr7Worker,
            router: $router,
            requestBridge: new Psr7RequestBridge(),
            responseBridge: new Psr7ResponseBridge(),
            logger: new WorkerLogger(),
            container: $container,
            development: true,
        );

        $handler->run();

        $body = (string) $psr7Worker->responses[0]->getBody();

        expect($psr7Worker->responses)->toHaveCount(1)
            ->and($psr7Worker->responses[0]->getStatusCode())->toBe(500)
            ->and($body)->toContain('reset failed for FaultyResettable');
    });

    it('performs resets in a deterministic order', function (): void {
        /** @var list<string> $log */
        $log = [];
        $container = new StubResettableContainer([
            'zeta' => new RecordingResettable(onReset: function () use (&$log): void {
                $log[] = 'zeta';
            }),
            'alpha' => new RecordingResettable(onReset: function () use (&$log): void {
                $log[] = 'alpha';
            }),
            'mid' => new RecordingResettable(onReset: function () use (&$log): void {
                $log[] = 'mid';
            }),
        ]);
        $router = new Router(new FakeRouteMatcher(), new NullContainer());
        $psr7Worker = new FakePsr7Worker([
            new ServerRequest('GET', 'https://example.test/one'),
            new ServerRequest('GET', 'https://example.test/two'),
        ]);
        $handler = new WorkerRequestHandler(
            psr7Worker: $psr7Worker,
            router: $router,
            requestBridge: new Psr7RequestBridge(),
            responseBridge: new Psr7ResponseBridge(),
            logger: new WorkerLogger(),
            container: $container,
        );

        $handler->run();

        expect($log)->toBe(['alpha', 'mid', 'zeta', 'alpha', 'mid', 'zeta']);
    });
});
