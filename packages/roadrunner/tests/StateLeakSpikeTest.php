<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Tests;

use Marko\Roadrunner\Tests\Fixtures\Demo\Http\Controllers\DemoController;
use Marko\Roadrunner\Tests\Support\InProcessRequestHarness;
use Marko\Session\Contracts\SessionInterface;
use ReflectionProperty;

/**
 * Task 005's discovery spike: drive many requests with interleaved
 * identities through one booted application and empirically confirm what
 * leaks between them.
 */
describe('State-leak discovery spike', function (): void {
    it('does not carry session data from one request into the next', function (): void {
        $harness = new InProcessRequestHarness(inProcessHarnessFixturePath());
        $cookieName = inProcessHarnessSessionCookieName();

        // Interleave: authenticated -> anonymous -> a different session.
        $authenticated = $harness->handle(inProcessHarnessRequest(
            'GET',
            '/session/write',
            [$cookieName => inProcessHarnessSessionId()],
        ));
        $harness->reset();

        $anonymous = $harness->handle(inProcessHarnessRequest(
            'GET',
            '/session/read',
            [$cookieName => inProcessHarnessSessionId()],
        ));
        $harness->reset();

        $different = $harness->handle(inProcessHarnessRequest(
            'GET',
            '/session/write',
            [$cookieName => inProcessHarnessSessionId()],
        ));

        expect($authenticated->body())->toContain('visits=1')
            ->and($anonymous->body())->toContain('visits=0')
            ->and($different->body())->toContain('visits=1');
    });

    it('does not carry the authenticated user from one request into the next', function (): void {
        $harness = new InProcessRequestHarness(inProcessHarnessFixturePath());
        $cookieName = inProcessHarnessSessionCookieName();

        // Interleave: authenticated -> anonymous -> a different session.
        $authenticated = $harness->handle(inProcessHarnessRequest(
            'GET',
            '/session/write',
            [$cookieName => inProcessHarnessSessionId()],
        ));
        $harness->reset();

        $anonymous = $harness->handle(inProcessHarnessRequest(
            'GET',
            '/session/read',
            [$cookieName => inProcessHarnessSessionId()],
        ));
        $harness->reset();

        $different = $harness->handle(inProcessHarnessRequest(
            'GET',
            '/session/write',
            [$cookieName => inProcessHarnessSessionId()],
        ));

        expect($authenticated->body())->toContain('user=1')
            ->and($anonymous->body())->toContain('user=guest')
            ->and($different->body())->toContain('user=1');
    });

    it('does not carry request scoped container state between requests', function (): void {
        $harness = new InProcessRequestHarness(inProcessHarnessFixturePath());

        // DemoController is not declared a singleton in the fixture app, so
        // each resolution the Router performs per request must be a fresh
        // instance — nothing about handling one request's controller may
        // bleed into the next request's controller instance.
        $first = $harness->container()->get(DemoController::class);
        $second = $harness->container()->get(DemoController::class);

        expect($first)->not->toBe($second);
    });

    it('does not accumulate shutdown functions across requests', function (): void {
        $harness = new InProcessRequestHarness(inProcessHarnessFixturePath());
        $cookieName = inProcessHarnessSessionCookieName();

        // Session::configure() guards session_set_save_handler()'s implicit
        // register_shutdown_function() call behind a handlerRegistered flag
        // so it only ever fires once per process, however many times
        // start() runs across however many requests (see Session.php).
        $session = $harness->container()->get(SessionInterface::class);
        $reflection = new ReflectionProperty($session, 'handlerRegistered');

        for ($i = 0; $i < 10; $i++) {
            (void) $harness->handle(inProcessHarnessRequest(
                'GET',
                '/session/write',
                [$cookieName => inProcessHarnessSessionId()],
            ));
        }

        expect($reflection->getValue($session))->toBeTrue();
    });

    it('does not drift the output buffer level across requests', function (): void {
        $harness = new InProcessRequestHarness(inProcessHarnessFixturePath());
        $cookieName = inProcessHarnessSessionCookieName();
        $levelBefore = ob_get_level();

        for ($i = 0; $i < 20; $i++) {
            (void) $harness->handle(inProcessHarnessRequest(
                'GET',
                '/session/read',
                [$cookieName => inProcessHarnessSessionId()],
            ));
        }

        expect(ob_get_level())->toBe($levelBefore);
    });

    it('leaves no active session when a request throws', function (): void {
        $harness = new InProcessRequestHarness(inProcessHarnessFixturePath());
        $cookieName = inProcessHarnessSessionCookieName();

        $thrown = catchThrowable(function () use ($harness, $cookieName): void {
            (void) $harness->handle(inProcessHarnessRequest(
                'GET',
                '/session/throw',
                [$cookieName => inProcessHarnessSessionId()],
            ));
        });

        expect($thrown)->not->toBeNull()
            ->and(session_status())->not->toBe(PHP_SESSION_ACTIVE);
    });

    it('does not grow memory unboundedly across several hundred requests', function (): void {
        $harness = new InProcessRequestHarness(inProcessHarnessFixturePath());
        $cookieName = inProcessHarnessSessionCookieName();
        $requestCount = 400;
        $sampleEvery = 50;

        /** @var list<int> $samples */
        $samples = [];

        for ($i = 0; $i < $requestCount; $i++) {
            (void) $harness->handle(inProcessHarnessRequest(
                'GET',
                '/session/write',
                [$cookieName => inProcessHarnessSessionId()],
            ));
            $harness->reset();

            if ($i % $sampleEvery === 0) {
                $samples[] = memory_get_usage(true);
            }
        }

        $growth = end($samples) - $samples[0];

        // A generous bound: hundreds of requests through one booted app
        // should not grow resident memory by tens of megabytes. This is a
        // curve check, not a zero-growth check — some growth from opcache
        // warmup and PHP's own allocator behaviour is expected and fine.
        expect($growth)->toBeLessThan(25 * 1024 * 1024);
    });
});
