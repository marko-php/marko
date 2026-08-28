<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Tests\Support;

use Marko\Core\Container\Container;

use function Marko\Roadrunner\Tests\inProcessHarnessFixturePath;

use function Marko\Roadrunner\Tests\inProcessHarnessRequest;
use function Marko\Roadrunner\Tests\inProcessHarnessSessionCookieName;
use function Marko\Roadrunner\Tests\inProcessHarnessSessionId;

use Marko\Session\Contracts\SessionInterface;

describe('InProcessRequestHarness', function (): void {
    it('boots the fixture application exactly once across many requests', function (): void {
        $harness = new InProcessRequestHarness(inProcessHarnessFixturePath());

        $firstResponse = $harness->handle(inProcessHarnessRequest('GET', '/session/read'));
        $containerAfterFirstRequest = $harness->container();

        $secondResponse = $harness->handle(inProcessHarnessRequest('GET', '/session/read'));
        $containerAfterSecondRequest = $harness->container();

        expect($containerAfterSecondRequest)->toBe($containerAfterFirstRequest)
            ->and($firstResponse->statusCode())->toBe(200)
            ->and($secondResponse->statusCode())->toBe(200);
    });

    it('returns a response for each request driven through the harness', function (): void {
        $harness = new InProcessRequestHarness(inProcessHarnessFixturePath());

        $write = $harness->handle(inProcessHarnessRequest('GET', '/session/write'));
        $read = $harness->handle(inProcessHarnessRequest('GET', '/session/read'));

        expect($write->statusCode())->toBe(200)
            ->and($write->body())->toContain('visits=1')
            ->and($read->statusCode())->toBe(200);
    });

    it('drives requests carrying different cookies', function (): void {
        $harness = new InProcessRequestHarness(inProcessHarnessFixturePath());
        $cookieName = inProcessHarnessSessionCookieName();

        $first = $harness->handle(inProcessHarnessRequest(
            'GET',
            '/session/write',
            [$cookieName => inProcessHarnessSessionId()],
        ));
        $second = $harness->handle(inProcessHarnessRequest(
            'GET',
            '/session/write',
            [$cookieName => inProcessHarnessSessionId()],
        ));

        expect($first->body())->not->toBe($second->body());
    });

    it('exposes the booted application container to the caller', function (): void {
        $harness = new InProcessRequestHarness(inProcessHarnessFixturePath());

        expect($harness->container())->toBeInstanceOf(Container::class)
            ->and($harness->container()->has(SessionInterface::class))->toBeTrue();
    });

    it('exposes a reset hook that runs between requests', function (): void {
        $harness = new InProcessRequestHarness(inProcessHarnessFixturePath());
        $cookieName = inProcessHarnessSessionCookieName();

        $first = $harness->handle(inProcessHarnessRequest(
            'GET',
            '/session/write',
            [$cookieName => inProcessHarnessSessionId()],
        ));

        $harness->reset();

        $second = $harness->handle(inProcessHarnessRequest(
            'GET',
            '/session/read',
            [$cookieName => inProcessHarnessSessionId()],
        ));

        expect($first->body())->toContain('user=1')
            ->and($second->body())->toContain('user=guest');
    });

    it('requires no roadrunner binary', function (): void {
        $harness = new InProcessRequestHarness(inProcessHarnessFixturePath());

        $response = $harness->handle(inProcessHarnessRequest('GET', '/session/read'));

        expect(getenv('RR_MODE'))->toBeFalse()
            ->and($response->statusCode())->toBe(200);
    });
});
