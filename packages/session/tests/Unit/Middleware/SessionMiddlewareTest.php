<?php

declare(strict_types=1);

use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Session\Config\SessionConfig;
use Marko\Session\Contracts\SessionInterface;
use Marko\Session\Exceptions\InvalidSessionIdException;
use Marko\Session\Flash\FlashBag;
use Marko\Session\Middleware\SessionMiddleware;
use Marko\Testing\Fake\FakeConfigRepository;

it('starts session before passing to next handler', function (): void {
    $sessionStarted = false;

    $session = createFakeSession(onStart: function () use (&$sessionStarted): void {
        $sessionStarted = true;
    });

    $middleware = new SessionMiddleware($session, createMiddlewareSessionConfig());

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

    $middleware = new SessionMiddleware($session, createMiddlewareSessionConfig());

    $request = new Request(server: [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/',
    ]);

    $middleware->handle($request, fn (Request $r) => new Response('OK'));

    expect($sessionSaved)->toBeTrue();
});

it('passes request through to next handler', function (): void {
    $session = createFakeSession();

    $middleware = new SessionMiddleware($session, createMiddlewareSessionConfig());

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

    $middleware = new SessionMiddleware($session, createMiddlewareSessionConfig());

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

    $middleware = new SessionMiddleware($session, createMiddlewareSessionConfig());

    $request = new Request(server: [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/',
    ]);

    $middleware->handle($request, fn (Request $r) => new Response('OK'));

    expect($startCount)->toBe(0);
});

it('reuses the session id from the inbound request cookie', function (): void {
    $inboundId = 'abcdefghijklmnopqrstuvwxyz012345';
    $capturedSetId = null;

    $session = createFakeSession(onSetId: function (string $id) use (&$capturedSetId): void {
        $capturedSetId = $id;
    });
    $sessionConfig = createMiddlewareSessionConfig();
    $middleware = new SessionMiddleware($session, $sessionConfig);

    $request = new Request(
        server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'],
        cookies: [$sessionConfig->cookieName() => $inboundId],
    );

    $middleware->handle($request, fn (Request $r) => new Response('OK'));

    expect($capturedSetId)->toBe($inboundId)
        ->and($session->getId())->toBe($inboundId);
});

it('ignores an invalid inbound session cookie and starts a fresh session', function (): void {
    $session = createFakeSession(rejectSetId: true);
    $sessionConfig = createMiddlewareSessionConfig();
    $middleware = new SessionMiddleware($session, $sessionConfig);

    $request = new Request(
        server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'],
        cookies: [$sessionConfig->cookieName() => 'not-a-valid-id'],
    );

    $response = $middleware->handle($request, fn (Request $r) => new Response('OK'));

    expect($response->body())->toBe('OK')
        ->and($session->getId())->not->toBe('not-a-valid-id')
        ->and($session->getId())->not->toBeEmpty();
});

it('attaches the session cookie to the response when the session is new', function (): void {
    $session = createFakeSession();
    $sessionConfig = createMiddlewareSessionConfig();
    $middleware = new SessionMiddleware($session, $sessionConfig);

    $request = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']);

    $response = $middleware->handle($request, fn (Request $r) => new Response('OK'));

    expect($response->cookies())->toHaveCount(1)
        ->and($response->cookies()[0]->name())->toBe($sessionConfig->cookieName());
});

it('does not attach a session cookie when the id is unchanged', function (): void {
    $inboundId = 'abcdefghijklmnopqrstuvwxyz012345';

    $session = createFakeSession();
    $sessionConfig = createMiddlewareSessionConfig();
    $middleware = new SessionMiddleware($session, $sessionConfig);

    $request = new Request(
        server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'],
        cookies: [$sessionConfig->cookieName() => $inboundId],
    );

    $response = $middleware->handle($request, fn (Request $r) => new Response('OK'));

    expect($response->cookies())->toBeEmpty();
});

it('attaches the new session cookie after the session id is regenerated', function (): void {
    $inboundId = 'abcdefghijklmnopqrstuvwxyz012345';

    $session = createFakeSession();
    $sessionConfig = createMiddlewareSessionConfig();
    $middleware = new SessionMiddleware($session, $sessionConfig);

    $request = new Request(
        server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'],
        cookies: [$sessionConfig->cookieName() => $inboundId],
    );

    $response = $middleware->handle($request, function (Request $r) use ($session): Response {
        $session->regenerate();

        return new Response('OK');
    });

    expect($response->cookies())->toHaveCount(1)
        ->and($response->cookies()[0]->name())->toBe($sessionConfig->cookieName())
        ->and($session->getId())->not->toBe($inboundId);
});

it('emits exactly one session set-cookie line for the configured cookie name', function (): void {
    $session = createFakeSession();
    $sessionConfig = createMiddlewareSessionConfig();
    $middleware = new SessionMiddleware($session, $sessionConfig);

    $request = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']);

    $response = $middleware->handle($request, fn (Request $r) => new Response('OK'));

    $setCookieLines = array_values(array_filter(
        $response->headerLines(),
        fn (string $line): bool => str_starts_with($line, 'Set-Cookie: ' . $sessionConfig->cookieName() . '='),
    ));

    expect($setCookieLines)->toHaveCount(1);
});

it('applies the configured lifetime path and domain to the session cookie', function (): void {
    $session = createFakeSession();
    $sessionConfig = createMiddlewareSessionConfig([
        'session.lifetime' => 30,
        'session.cookie.path' => '/app',
        'session.cookie.domain' => 'example.test',
    ]);
    $middleware = new SessionMiddleware($session, $sessionConfig);

    $request = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']);

    $response = $middleware->handle($request, fn (Request $r) => new Response('OK'));

    $cookieLine = $response->cookies()[0]->toSetCookieString();
    preg_match('/Expires=([^;]+)/', $cookieLine, $matches);
    $expiresAt = strtotime($matches[1]);
    $expectedExpiresAt = time() + 30 * 60;

    expect($cookieLine)->toContain('Path=/app')
        ->and($cookieLine)->toContain('Domain=example.test')
        ->and(abs($expiresAt - $expectedExpiresAt))->toBeLessThan(5);
});

it('marks the session cookie httponly and applies the configured samesite value', function (): void {
    $session = createFakeSession();
    $sessionConfig = createMiddlewareSessionConfig([
        'session.cookie.httponly' => true,
        'session.cookie.samesite' => 'strict',
    ]);
    $middleware = new SessionMiddleware($session, $sessionConfig);

    $request = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']);

    $response = $middleware->handle($request, fn (Request $r) => new Response('OK'));

    $cookieLine = $response->cookies()[0]->toSetCookieString();

    expect($cookieLine)->toContain('HttpOnly')
        ->and($cookieLine)->toContain('SameSite=Strict');
});

it('attaches an expired cookie to the response when the session is destroyed', function (): void {
    $inboundId = 'abcdefghijklmnopqrstuvwxyz012345';

    $session = createFakeSession();
    $sessionConfig = createMiddlewareSessionConfig();
    $middleware = new SessionMiddleware($session, $sessionConfig);

    $request = new Request(
        server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'],
        cookies: [$sessionConfig->cookieName() => $inboundId],
    );

    $response = $middleware->handle($request, function (Request $r) use ($session): Response {
        $session->destroy();

        return new Response('OK');
    });

    $cookieLine = $response->cookies()[0]->toSetCookieString();
    preg_match('/Expires=([^;]+)/', $cookieLine, $matches);

    expect($response->cookies())->toHaveCount(1)
        ->and($response->cookies()[0]->name())->toBe($sessionConfig->cookieName())
        ->and(strtotime($matches[1]))->toBeLessThan(time());
});

it('still saves the session when the handler throws and attaches no cookie', function (): void {
    $sessionSaved = false;

    $session = createFakeSession(onSave: function () use (&$sessionSaved): void {
        $sessionSaved = true;
    });
    $sessionConfig = createMiddlewareSessionConfig();
    $middleware = new SessionMiddleware($session, $sessionConfig);

    $request = new Request(server: [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/',
    ]);

    $thrown = null;

    try {
        $middleware->handle($request, function () {
            throw new RuntimeException('handler error');
        });
    } catch (RuntimeException $exception) {
        $thrown = $exception;
    }

    expect($thrown)->toBeInstanceOf(RuntimeException::class)
        ->and($sessionSaved)->toBeTrue();
});

/**
 * Create a SessionConfig backed by a FakeConfigRepository for middleware tests.
 *
 * @param array<string, mixed> $overrides
 */
function createMiddlewareSessionConfig(array $overrides = []): SessionConfig
{
    return new SessionConfig(new FakeConfigRepository([
        'session.driver' => 'array',
        'session.lifetime' => 120,
        'session.expire_on_close' => false,
        'session.path' => '/tmp',
        'session.cookie.name' => 'marko_session',
        'session.cookie.path' => '/',
        'session.cookie.domain' => '',
        'session.cookie.secure' => true,
        'session.cookie.httponly' => true,
        'session.cookie.samesite' => 'lax',
        'session.gc_probability' => 2,
        'session.gc_divisor' => 100,
        ...$overrides,
    ]));
}

/**
 * Create a fake session for testing middleware behavior.
 */
function createFakeSession(
    bool $started = false,
    ?Closure $onStart = null,
    ?Closure $onSave = null,
    bool $rejectSetId = false,
    ?Closure $onSetId = null,
): SessionInterface {
    return new class ($started, $onStart, $onSave, $rejectSetId, $onSetId) implements SessionInterface
    {
        private string $id = '';

        public function __construct(
            public bool $started,
            private readonly ?Closure $onStart,
            private readonly ?Closure $onSave,
            private readonly bool $rejectSetId,
            private readonly ?Closure $onSetId,
        ) {}

        public function start(): void
        {
            if ($this->onStart !== null) {
                ($this->onStart)();
            }

            if ($this->id === '') {
                $this->id = 'generated-session-id-1234567890123456';
            }

            $this->started = true;
        }

        public function save(): void
        {
            if ($this->onSave !== null) {
                ($this->onSave)();
            }

            $this->started = false;
        }

        public function get(
            string $key,
            mixed $default = null,
        ): mixed {
            return $default;
        }

        public function set(
            string $key,
            mixed $value,
        ): void {}

        public function has(string $key): bool
        {
            return false;
        }

        public function remove(string $key): void {}

        public function clear(): void {}

        /**
         * @return array<string, mixed>
         */
        public function all(): array
        {
            return [];
        }

        public function regenerate(bool $deleteOldSession = true): void
        {
            $this->id = 'regenerated-session-id-1234567890123456';
        }

        public function destroy(): void
        {
            $this->id = '';
            $this->started = false;
        }

        public function getId(): string
        {
            return $this->id;
        }

        /**
         * @throws InvalidSessionIdException
         */
        public function setId(string $id): void
        {
            if ($this->onSetId !== null) {
                ($this->onSetId)($id);
            }

            if ($this->rejectSetId) {
                throw InvalidSessionIdException::forId($id);
            }

            $this->id = $id;
        }

        public function flash(): FlashBag
        {
            return new FlashBag([]);
        }
    };
}
