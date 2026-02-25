<?php

declare(strict_types=1);

use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Session\Contracts\SessionInterface;
use Marko\Session\Flash\FlashBag;
use Marko\Session\Middleware\SessionMiddleware;

it('starts session before passing to next handler', function (): void {
    $sessionStarted = false;

    $session = createFakeSession(onStart: function () use (&$sessionStarted): void {
        $sessionStarted = true;
    });

    $middleware = new SessionMiddleware($session);

    $request = new Request(server: [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/',
    ]);

    $middleware->handle($request, fn (Request $r) => new Response('OK'));

    expect($sessionStarted)->toBeTrue();
});

it('saves session after response', function (): void {
    $sessionSaved = false;

    $session = createFakeSession(onSave: function () use (&$sessionSaved): void {
        $sessionSaved = true;
    });

    $middleware = new SessionMiddleware($session);

    $request = new Request(server: [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/',
    ]);

    $middleware->handle($request, fn (Request $r) => new Response('OK'));

    expect($sessionSaved)->toBeTrue();
});

it('passes request through to next handler', function (): void {
    $session = createFakeSession();

    $middleware = new SessionMiddleware($session);

    $request = new Request(server: [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/test',
    ]);

    $response = $middleware->handle($request, fn (Request $r) => new Response('from handler'));

    expect($response->body())->toBe('from handler');
});

it('saves session even when handler throws', function (): void {
    $sessionSaved = false;

    $session = createFakeSession(onSave: function () use (&$sessionSaved): void {
        $sessionSaved = true;
    });

    $middleware = new SessionMiddleware($session);

    $request = new Request(server: [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/',
    ]);

    try {
        $middleware->handle($request, function () {
            throw new RuntimeException('handler error');
        });
    } catch (RuntimeException) {
        // Expected
    }

    expect($sessionSaved)->toBeTrue();
});

it('does not start session if already started', function (): void {
    $startCount = 0;

    $session = createFakeSession(
        started: true,
        onStart: function () use (&$startCount): void {
            $startCount++;
        },
    );

    $middleware = new SessionMiddleware($session);

    $request = new Request(server: [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/',
    ]);

    $middleware->handle($request, fn (Request $r) => new Response('OK'));

    expect($startCount)->toBe(0);
});

/**
 * Create a fake session for testing middleware behavior.
 *
 * @return SessionInterface
 */
function createFakeSession(
    bool $started = false,
    ?Closure $onStart = null,
    ?Closure $onSave = null,
): SessionInterface {
    return new class ($started, $onStart, $onSave) implements SessionInterface
    {
        public function __construct(
            public bool $started,
            private readonly ?Closure $onStart,
            private readonly ?Closure $onSave,
        ) {}

        public function start(): void
        {
            if ($this->onStart !== null) {
                ($this->onStart)();
            }
            $this->started = true;
        }

        public function save(): void
        {
            if ($this->onSave !== null) {
                ($this->onSave)();
            }
        }

        public function get(string $key, mixed $default = null): mixed
        {
            return $default;
        }

        public function set(string $key, mixed $value): void {}

        public function has(string $key): bool
        {
            return false;
        }

        public function remove(string $key): void {}

        public function clear(): void {}

        public function all(): array
        {
            return [];
        }

        public function regenerate(bool $deleteOldSession = true): void {}

        public function destroy(): void {}

        public function getId(): string
        {
            return '';
        }

        public function setId(string $id): void {}

        public function flash(): FlashBag
        {
            return new FlashBag([]);
        }
    };
}
