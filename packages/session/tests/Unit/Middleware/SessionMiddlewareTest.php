<?php

declare(strict_types=1);

use Marko\Routing\Attributes\RunsOnUnmatched;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Session\Config\SessionConfig;
use Marko\Session\Contracts\SessionInterface;
use Marko\Session\Exceptions\InvalidSessionIdException;
use Marko\Session\Flash\FlashBag;
use Marko\Session\Middleware\SessionMiddleware;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;

it('starts the session eagerly when the request carries a session cookie', function (): void {
    $startedBeforeNext = null;
    $session = createFakeSession();
    $sessionConfig = createMiddlewareSessionConfig();
    $middleware = new SessionMiddleware($session, $sessionConfig, new FakeClock());

    $request = new Request(
        server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'],
        cookies: [$sessionConfig->cookieName() => 'abcdefghijklmnopqrstuvwxyz012345'],
    );

    $middleware->handle($request, function (Request $r) use ($session, &$startedBeforeNext): Response {
        $startedBeforeNext = $session->started;

        return new Response('OK');
    });

    expect($startedBeforeNext)->toBeTrue();
});

it('arms instead of starting the session when the request has no session cookie', function (): void {
    $startCount = 0;
    $state = null;
    $session = createFakeSession(onStart: function () use (&$startCount): void {
        $startCount++;
    });
    $middleware = new SessionMiddleware($session, createMiddlewareSessionConfig(), new FakeClock());

    $request = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']);

    $middleware->handle($request, function (Request $r) use ($session, &$state): Response {
        $state = ['started' => $session->started, 'available' => $session->isAvailable()];

        return new Response('OK');
    });

    expect($state)->toBe(['started' => false, 'available' => true])
        ->and($startCount)->toBe(0)
        ->and($session->isAvailable())->toBeFalse();
});

it('arms instead of starting the session when the inbound session cookie is malformed', function (): void {
    $startCount = 0;
    $session = createFakeSession(
        onStart: function () use (&$startCount): void {
            $startCount++;
        },
        rejectSetId: true,
    );
    $sessionConfig = createMiddlewareSessionConfig();
    $middleware = new SessionMiddleware($session, $sessionConfig, new FakeClock());

    $request = new Request(
        server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'],
        cookies: [$sessionConfig->cookieName() => 'not-a-valid-id'],
    );

    $middleware->handle($request, fn (Request $r) => new Response('OK'));

    expect($startCount)->toBe(0);
});

it('does not re-arm or start a session that is already available', function (): void {
    $startCount = 0;
    $session = createFakeSession(onStart: function () use (&$startCount): void {
        $startCount++;
    });
    $session->arm();
    $sessionConfig = createMiddlewareSessionConfig();
    $middleware = new SessionMiddleware($session, $sessionConfig, new FakeClock());

    $middleware->handle(
        new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'],
            cookies: [$sessionConfig->cookieName() => 'abcdefghijklmnopqrstuvwxyz012345'],
        ),
        fn (Request $r) => new Response('OK'),
    );

    expect($startCount)->toBe(0)
        ->and($session->getId())->toBe('');
});

it('starts an armed session lazily when the request first uses it', function (): void {
    $startCount = 0;
    $session = createFakeSession(onStart: function () use (&$startCount): void {
        $startCount++;
    });
    $middleware = new SessionMiddleware($session, createMiddlewareSessionConfig(), new FakeClock());

    $middleware->handle(
        new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']),
        function (Request $r) use ($session): Response {
            $session->get('user_id');
            $session->get('cart');

            return new Response('OK');
        },
    );

    expect($startCount)->toBe(1);
});

it('saves session after response', function (): void {
    $sessionSaved = false;

    $session = createFakeSession(onSave: function () use (&$sessionSaved): void {
        $sessionSaved = true;
    });

    $middleware = new SessionMiddleware($session, createMiddlewareSessionConfig(), new FakeClock());

    $request = new Request(server: [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/',
    ]);

    $middleware->handle($request, writingHandler($session));

    expect($sessionSaved)->toBeTrue();
});

it('passes request through to next handler', function (): void {
    $session = createFakeSession();

    $middleware = new SessionMiddleware($session, createMiddlewareSessionConfig(), new FakeClock());

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

    $middleware = new SessionMiddleware($session, createMiddlewareSessionConfig(), new FakeClock());

    $request = new Request(server: [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/',
    ]);

    try {
        $middleware->handle($request, function () use ($session) {
            $session->set('key', 'value');

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

    $middleware = new SessionMiddleware($session, createMiddlewareSessionConfig(), new FakeClock());

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
    $middleware = new SessionMiddleware($session, $sessionConfig, new FakeClock());

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
    $middleware = new SessionMiddleware($session, $sessionConfig, new FakeClock());

    $request = new Request(
        server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'],
        cookies: [$sessionConfig->cookieName() => 'not-a-valid-id'],
    );

    $response = $middleware->handle($request, writingHandler($session));

    expect($response->body())->toBe('OK')
        ->and($session->getId())->not->toBe('not-a-valid-id')
        ->and($session->getId())->not->toBeEmpty();
});

it('attaches the session cookie to the response when the session is new', function (): void {
    $session = createFakeSession();
    $sessionConfig = createMiddlewareSessionConfig();
    $middleware = new SessionMiddleware($session, $sessionConfig, new FakeClock());

    $request = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']);

    $response = $middleware->handle($request, writingHandler($session));

    expect($response->cookies())->toHaveCount(1)
        ->and($response->cookies()[0]->name())->toBe($sessionConfig->cookieName());
});

it('does not attach a session cookie when the id is unchanged', function (): void {
    $inboundId = 'abcdefghijklmnopqrstuvwxyz012345';

    $session = createFakeSession();
    $sessionConfig = createMiddlewareSessionConfig();
    $middleware = new SessionMiddleware($session, $sessionConfig, new FakeClock());

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
    $middleware = new SessionMiddleware($session, $sessionConfig, new FakeClock());

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
    $middleware = new SessionMiddleware($session, $sessionConfig, new FakeClock());

    $request = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']);

    $response = $middleware->handle($request, writingHandler($session));

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
    $middleware = new SessionMiddleware($session, $sessionConfig, new FakeClock());

    $request = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']);

    $response = $middleware->handle($request, writingHandler($session));

    $cookieLine = $response->cookies()[0]->toSetCookieString();

    expect($cookieLine)->toContain('Path=/app')
        ->and($cookieLine)->toContain('Domain=example.test');
});

it('sets the session cookie expiry to now plus the configured lifetime', function (): void {
    $clock = new FakeClock('2026-01-01 12:00:00 UTC');
    $session = createFakeSession();
    $middleware = new SessionMiddleware(
        $session,
        createMiddlewareSessionConfig(['session.lifetime' => 30]),
        $clock,
    );

    $request = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']);

    $response = $middleware->handle($request, writingHandler($session));

    preg_match('/Expires=([^;]+)/', $response->cookies()[0]->toSetCookieString(), $matches);

    expect(strtotime($matches[1]))->toBe($clock->now()->getTimestamp() + 30 * 60);
});

it('marks the session cookie httponly and applies the configured samesite value', function (): void {
    $session = createFakeSession();
    $sessionConfig = createMiddlewareSessionConfig([
        'session.cookie.httponly' => true,
        'session.cookie.samesite' => 'strict',
    ]);
    $middleware = new SessionMiddleware($session, $sessionConfig, new FakeClock());

    $request = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']);

    $response = $middleware->handle($request, writingHandler($session));

    $cookieLine = $response->cookies()[0]->toSetCookieString();

    expect($cookieLine)->toContain('HttpOnly')
        ->and($cookieLine)->toContain('SameSite=Strict');
});

it('expires the session cookie relative to the clock when the session is destroyed', function (): void {
    $inboundId = 'abcdefghijklmnopqrstuvwxyz012345';

    $clock = new FakeClock('2026-01-01 12:00:00 UTC');
    $session = createFakeSession();
    $sessionConfig = createMiddlewareSessionConfig();
    $middleware = new SessionMiddleware($session, $sessionConfig, $clock);

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
        ->and(strtotime($matches[1]))->toBe($clock->now()->getTimestamp() - 42000);
});

it('still saves the session when the handler throws and attaches no cookie', function (): void {
    $sessionSaved = false;

    $session = createFakeSession(onSave: function () use (&$sessionSaved): void {
        $sessionSaved = true;
    });
    $sessionConfig = createMiddlewareSessionConfig();
    $middleware = new SessionMiddleware($session, $sessionConfig, new FakeClock());

    $request = new Request(server: [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/',
    ]);

    $thrown = null;

    try {
        $middleware->handle($request, function () use ($session) {
            $session->set('key', 'value');

            throw new RuntimeException('handler error');
        });
    } catch (RuntimeException $exception) {
        $thrown = $exception;
    }

    expect($thrown)->toBeInstanceOf(RuntimeException::class)
        ->and($sessionSaved)->toBeTrue();
});

describe('lazy persistence', function (): void {
    it('discards an untouched new session without saving it', function (): void {
        $saved = false;
        $discarded = false;

        $session = createFakeSession(
            onSave: function () use (&$saved): void {
                $saved = true;
            },
            onDiscard: function () use (&$discarded): void {
                $discarded = true;
            },
        );
        $middleware = new SessionMiddleware($session, createMiddlewareSessionConfig(), new FakeClock());

        $middleware->handle(
            new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']),
            fn (Request $r) => new Response('OK'),
        );

        expect($saved)->toBeFalse()
            ->and($discarded)->toBeTrue();
    });

    it('sets no session cookie when a new session is never modified', function (): void {
        $session = createFakeSession();
        $middleware = new SessionMiddleware($session, createMiddlewareSessionConfig(), new FakeClock());

        $response = $middleware->handle(
            new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']),
            function (Request $r) use ($session): Response {
                $session->get('user_id');

                return new Response('OK');
            },
        );

        expect($response->cookies())->toBeEmpty();
    });

    it('saves a new session and attaches the cookie once it is written to', function (): void {
        $saved = false;

        $session = createFakeSession(onSave: function () use (&$saved): void {
            $saved = true;
        });
        $sessionConfig = createMiddlewareSessionConfig();
        $middleware = new SessionMiddleware($session, $sessionConfig, new FakeClock());

        $response = $middleware->handle(
            new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']),
            writingHandler($session),
        );

        expect($saved)->toBeTrue()
            ->and($response->cookies())->toHaveCount(1)
            ->and($response->cookies()[0]->name())->toBe($sessionConfig->cookieName());
    });

    it('saves an existing session on every request even when unmodified', function (): void {
        $saved = false;

        $session = createFakeSession(onSave: function () use (&$saved): void {
            $saved = true;
        });
        $sessionConfig = createMiddlewareSessionConfig();
        $middleware = new SessionMiddleware($session, $sessionConfig, new FakeClock());

        $response = $middleware->handle(
            new Request(
                server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'],
                cookies: [$sessionConfig->cookieName() => 'abcdefghijklmnopqrstuvwxyz012345'],
            ),
            fn (Request $r) => new Response('OK'),
        );

        expect($saved)->toBeTrue()
            ->and($response->cookies())->toBeEmpty();
    });

    it('discards an untouched session and expires a malformed inbound session cookie', function (): void {
        $saved = false;

        $session = createFakeSession(
            onSave: function () use (&$saved): void {
                $saved = true;
            },
            rejectSetId: true,
        );
        $sessionConfig = createMiddlewareSessionConfig();
        $middleware = new SessionMiddleware($session, $sessionConfig, new FakeClock());

        $response = $middleware->handle(
            new Request(
                server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'],
                cookies: [$sessionConfig->cookieName() => 'not-a-valid-id'],
            ),
            fn (Request $r) => new Response('OK'),
        );

        expect($saved)->toBeFalse()
            ->and($response->cookies())->toHaveCount(1)
            ->and($response->cookies()[0]->value())->toBe('');
    });

    it('emits no cookie at all when a session the client never had is destroyed', function (): void {
        $session = createFakeSession();
        $middleware = new SessionMiddleware($session, createMiddlewareSessionConfig(), new FakeClock());

        $response = $middleware->handle(
            new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']),
            function (Request $r) use ($session): Response {
                $session->set('key', 'value');
                $session->destroy();

                return new Response('OK');
            },
        );

        expect($response->cookies())->toBeEmpty();
    });

    it('still discards an untouched new session when the handler throws', function (): void {
        $discarded = false;

        $session = createFakeSession(onDiscard: function () use (&$discarded): void {
            $discarded = true;
        });
        $middleware = new SessionMiddleware($session, createMiddlewareSessionConfig(), new FakeClock());

        try {
            $middleware->handle(
                new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']),
                function (): never {
                    throw new RuntimeException('handler error');
                },
            );
        } catch (RuntimeException) {
            // Expected
        }

        expect($discarded)->toBeTrue();
    });
});

describe('strict session ids', function (): void {
    it(
        'does not write and sends an expired cookie for a well-formed cookie the store does not know',
        function (): void {
            $saved = false;
            $clock = new FakeClock('2026-01-01 12:00:00 UTC');

            $session = createFakeSession(
                onSave: function () use (&$saved): void {
                    $saved = true;
                },
                rejectOnStart: true,
            );
            $sessionConfig = createMiddlewareSessionConfig();
            $middleware = new SessionMiddleware($session, $sessionConfig, $clock);

            $response = $middleware->handle(
                new Request(
                    server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'],
                    cookies: [$sessionConfig->cookieName() => str_repeat('a', 40)],
                ),
                fn (Request $r) => new Response('OK'),
            );

            $cookie = $response->cookies()[0];
            preg_match('/Expires=([^;]+)/', $cookie->toSetCookieString(), $matches);

            expect($saved)->toBeFalse()
                ->and($response->cookies())->toHaveCount(1)
                ->and($cookie->name())->toBe($sessionConfig->cookieName())
                ->and($cookie->value())->toBe('')
                ->and(strtotime($matches[1]))->toBeLessThan($clock->now()->getTimestamp());
        },
    );

    it('keeps the session unmodified for a rejected cookie', function (): void {
        $session = createFakeSession(rejectOnStart: true);
        $sessionConfig = createMiddlewareSessionConfig();
        $middleware = new SessionMiddleware($session, $sessionConfig, new FakeClock());
        $modifiedDuringRequest = null;

        $middleware->handle(
            new Request(
                server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'],
                cookies: [$sessionConfig->cookieName() => str_repeat('a', 40)],
            ),
            function (Request $r) use ($session, &$modifiedDuringRequest): Response {
                $modifiedDuringRequest = $session->isModified();

                return new Response('OK');
            },
        );

        expect($modifiedDuringRequest)->toBeFalse()
            ->and($session->getId())->not->toBe(str_repeat('a', 40));
    });

    it('sends an expired cookie for a malformed session cookie', function (): void {
        $session = createFakeSession(rejectSetId: true);
        $sessionConfig = createMiddlewareSessionConfig();
        $middleware = new SessionMiddleware($session, $sessionConfig, new FakeClock());

        $response = $middleware->handle(
            new Request(
                server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'],
                cookies: [$sessionConfig->cookieName() => 'not-a-valid-id'],
            ),
            fn (Request $r) => new Response('OK'),
        );

        expect($response->cookies())->toHaveCount(1)
            ->and($response->cookies()[0]->name())->toBe($sessionConfig->cookieName())
            ->and($response->cookies()[0]->value())->toBe('');
    });

    it('replaces a rejected cookie with a fresh one when the request writes to the session', function (): void {
        $saved = false;

        $session = createFakeSession(
            onSave: function () use (&$saved): void {
                $saved = true;
            },
            rejectOnStart: true,
        );
        $sessionConfig = createMiddlewareSessionConfig();
        $middleware = new SessionMiddleware($session, $sessionConfig, new FakeClock());

        $response = $middleware->handle(
            new Request(
                server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'],
                cookies: [$sessionConfig->cookieName() => str_repeat('a', 40)],
            ),
            writingHandler($session),
        );

        expect($saved)->toBeTrue()
            ->and($response->cookies())->toHaveCount(1)
            ->and($response->cookies()[0]->value())->toBe('generated-session-id-1234567890123456');
    });
});

/**
 * A handler that writes to the session, so the middleware persists it.
 */
function writingHandler(
    SessionInterface $session,
): Closure {
    return function (Request $r) use ($session): Response {
        $session->set('key', 'value');

        return new Response('OK');
    };
}

/**
 * Create a SessionConfig backed by a FakeConfigRepository for middleware tests.
 *
 * @param array<string, mixed> $overrides
 */
function createMiddlewareSessionConfig(
    array $overrides = [],
): SessionConfig {
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
    ?Closure $onDiscard = null,
    bool $rejectOnStart = false,
): SessionInterface {
    return new class ($started, $onStart, $onSave, $rejectSetId, $onSetId, $onDiscard, $rejectOnStart) implements SessionInterface
    {
        private string $id = '';

        private bool $modified = false;

        public bool $armed = false;

        public function __construct(
            public bool $started,
            private readonly ?Closure $onStart,
            private readonly ?Closure $onSave,
            private readonly bool $rejectSetId,
            private readonly ?Closure $onSetId,
            private readonly ?Closure $onDiscard,
            private readonly bool $rejectOnStart,
        ) {}

        public function arm(): void
        {
            $this->armed = true;
        }

        public function isAvailable(): bool
        {
            return $this->started
                || $this->armed;
        }

        /**
         * Mirrors Session: an armed session starts on first data access.
         */
        private function startIfArmed(): void
        {
            if (
                !$this->started
                && $this->armed
            ) {
                $this->start();
            }
        }

        public function isModified(): bool
        {
            return $this->started
                && $this->modified;
        }

        public function discard(): void
        {
            if ($this->onDiscard !== null) {
                ($this->onDiscard)();
            }

            $this->armed = false;
            $this->started = false;
        }

        public function start(): void
        {
            if ($this->onStart !== null) {
                ($this->onStart)();
            }

            // rejectOnStart mimics PHP strict mode with a handler whose
            // validateId() does not know the seeded id: it is replaced.
            if (
                $this->id === ''
                || $this->rejectOnStart
            ) {
                $this->id = 'generated-session-id-1234567890123456';
            }

            $this->started = true;
        }

        public function save(): void
        {
            if ($this->onSave !== null) {
                ($this->onSave)();
            }

            $this->armed = false;
            $this->started = false;
        }

        public function get(
            string $key,
            mixed $default = null,
        ): mixed {
            $this->startIfArmed();

            return $default;
        }

        public function set(
            string $key,
            mixed $value,
        ): void {
            $this->startIfArmed();
            $this->modified = true;
        }

        public function has(
            string $key,
        ): bool {
            $this->startIfArmed();

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

        public function regenerate(
            bool $deleteOldSession = true,
        ): void {
            $this->startIfArmed();
            $this->id = 'regenerated-session-id-1234567890123456';
            $this->modified = true;
        }

        public function destroy(): void
        {
            $this->id = '';
            $this->armed = false;
            $this->started = false;
        }

        public function getId(): string
        {
            return $this->id;
        }

        /**
         * @throws InvalidSessionIdException
         */
        public function setId(
            string $id,
        ): void {
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
            $this->startIfArmed();

            return new FlashBag([]);
        }
    };
}

it('does not run on unmatched requests, so 404s never touch the session store', function (): void {
    $attributes = new ReflectionClass(SessionMiddleware::class)->getAttributes(RunsOnUnmatched::class);

    expect($attributes)->toBe([]);
})->issue(267);
