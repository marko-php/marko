<?php

declare(strict_types=1);

namespace Marko\Session\File\Tests\Feature;

use Marko\Core\Path\ProjectPaths;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Session\Config\SessionConfig;
use Marko\Session\Contracts\SessionHandlerInterface;
use Marko\Session\File\Handler\FileSessionHandler;
use Marko\Session\Middleware\SessionMiddleware;
use Marko\Session\Session;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;

/**
 * Delegates to the real file handler and records which write path PHP used.
 */
class RecordingFileSessionHandler implements SessionHandlerInterface
{
    /** @var array<int, string> */
    public array $calls = [];

    public function __construct(
        private readonly FileSessionHandler $fileSessionHandler,
    ) {}

    public function open(
        string $path,
        string $name,
    ): bool {
        return $this->fileSessionHandler->open($path, $name);
    }

    public function close(): bool
    {
        return $this->fileSessionHandler->close();
    }

    public function read(
        string $id,
    ): string|false {
        return $this->fileSessionHandler->read($id);
    }

    public function write(
        string $id,
        string $data,
    ): bool {
        $this->calls[] = 'write';

        return $this->fileSessionHandler->write($id, $data);
    }

    public function destroy(
        string $id,
    ): bool {
        return $this->fileSessionHandler->destroy($id);
    }

    public function gc(
        int $max_lifetime,
    ): int|false {
        return $this->fileSessionHandler->gc($max_lifetime);
    }

    public function validateId(
        string $id,
    ): bool {
        return $this->fileSessionHandler->validateId($id);
    }

    public function updateTimestamp(
        string $id,
        string $data,
    ): bool {
        $this->calls[] = 'updateTimestamp';

        return $this->fileSessionHandler->updateTimestamp($id, $data);
    }
}

/**
 * @return array{middleware: SessionMiddleware, session: Session, handler: RecordingFileSessionHandler, clock: FakeClock}
 */
function strictFileSessionHarness(
    string $path,
): array {
    $config = new SessionConfig(new FakeConfigRepository([
        'session.driver' => 'file',
        'session.lifetime' => 120,
        'session.expire_on_close' => false,
        'session.path' => $path,
        'session.cookie.name' => 'marko_session',
        'session.cookie.path' => '/',
        'session.cookie.domain' => '',
        'session.cookie.secure' => true,
        'session.cookie.httponly' => true,
        'session.cookie.samesite' => 'lax',
        'session.gc_probability' => 0,
        'session.gc_divisor' => 100,
    ]));
    $clock = new FakeClock();
    $handler = new RecordingFileSessionHandler(
        new FileSessionHandler($config, $clock, new ProjectPaths(sys_get_temp_dir())),
    );
    $session = new Session($handler, $config);

    return [
        'middleware' => new SessionMiddleware($session, $config, $clock),
        'session' => $session,
        'handler' => $handler,
        'clock' => $clock,
    ];
}

/**
 * One request through the middleware, resetting the session afterwards the
 * way a long-running worker does between requests.
 */
function strictFileSessionRequest(
    array $harness,
    ?string $sessionId,
    callable $controller,
): Response {
    $request = new Request(
        server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'],
        cookies: $sessionId === null ? [] : ['marko_session' => $sessionId],
    );

    try {
        return $harness['middleware']->handle($request, $controller);
    } finally {
        $harness['session']->reset();
    }
}

/**
 * @return array<int, string>
 */
function strictFileSessionFiles(
    string $path,
): array {
    return glob($path . '/sess_*') ?: [];
}

beforeEach(function (): void {
    $this->sessionPath = sys_get_temp_dir() . '/marko-strict-session-' . bin2hex(random_bytes(8));
});

afterEach(function (): void {
    foreach (strictFileSessionFiles($this->sessionPath) as $file) {
        unlink($file);
    }

    if (is_dir($this->sessionPath)) {
        rmdir($this->sessionPath);
    }
});

it('creates no session file when an unknown cookie is replayed repeatedly', function (): void {
    $harness = strictFileSessionHarness($this->sessionPath);
    $unknownId = str_repeat('a', 40);
    $cookieValues = [];

    for ($i = 0; $i < 5; $i++) {
        $response = strictFileSessionRequest($harness, $unknownId, fn () => new Response('home'));
        $cookieValues[] = $response->cookies()[0]->value();
    }

    expect(strictFileSessionFiles($this->sessionPath))->toBe([])
        ->and($harness['handler']->calls)->toBe([])
        ->and($cookieValues)->toBe(['', '', '', '', '']);
})->issue(266);

it('treats a session file older than the lifetime as unknown', function (): void {
    $harness = strictFileSessionHarness($this->sessionPath);
    $issued = strictFileSessionRequest($harness, null, function () use ($harness): Response {
        $harness['session']->set('cart', ['sku-1']);

        return new Response('cart');
    });
    $sessionId = $issued->cookies()[0]->value();

    $harness['clock']->travel('+121 minutes');
    $session = $harness['session'];
    $seen = null;
    $response = strictFileSessionRequest($harness, $sessionId, function () use ($session, &$seen): Response {
        $seen = $session->get('cart');

        return new Response('home');
    });

    expect($seen)->toBeNull()
        ->and($response->cookies()[0]->value())->toBe('');
})->issue(266);

it('resumes a known file session and refreshes its timestamp without rewriting it', function (): void {
    $harness = strictFileSessionHarness($this->sessionPath);
    $issued = strictFileSessionRequest($harness, null, function () use ($harness): Response {
        $harness['session']->set('cart', ['sku-1']);

        return new Response('cart');
    });
    $sessionId = $issued->cookies()[0]->value();
    $file = $this->sessionPath . '/sess_' . $sessionId;
    $payload = file_get_contents($file);

    $harness['handler']->calls = [];
    $harness['clock']->travel('+30 minutes');
    $session = $harness['session'];
    $seen = null;
    $response = strictFileSessionRequest($harness, $sessionId, function () use ($session, &$seen): Response {
        $seen = $session->get('cart');

        return new Response('home');
    });
    clearstatcache(true, $file);

    expect($seen)->toBe(['sku-1'])
        ->and($response->cookies())->toBeEmpty()
        ->and($harness['handler']->calls)->toBe(['updateTimestamp'])
        ->and(file_get_contents($file))->toBe($payload)
        ->and(filemtime($file))->toBe($harness['clock']->now()->getTimestamp());
})->issue(266);

it(
    'sends no cookie when a session is created and destroyed in one request without an inbound cookie',
    function (): void {
        $harness = strictFileSessionHarness($this->sessionPath);
        $session = $harness['session'];

        $response = strictFileSessionRequest($harness, null, function () use ($session): Response {
            $session->set('cart', ['sku-1']);
            $session->destroy();

            return new Response('home');
        });

        expect($response->cookies())->toBeEmpty()
            ->and(strictFileSessionFiles($this->sessionPath))->toBe([]);
    },
)->issue(266);

it('sends an expired cookie and removes the file when destroying a session the client had', function (): void {
    $harness = strictFileSessionHarness($this->sessionPath);
    $issued = strictFileSessionRequest($harness, null, function () use ($harness): Response {
        $harness['session']->set('cart', ['sku-1']);

        return new Response('cart');
    });
    $sessionId = $issued->cookies()[0]->value();
    $session = $harness['session'];

    $response = strictFileSessionRequest($harness, $sessionId, function () use ($session): Response {
        $session->destroy();

        return new Response('logout');
    });

    expect($response->cookies())->toHaveCount(1)
        ->and($response->cookies()[0]->value())->toBe('')
        ->and(strictFileSessionFiles($this->sessionPath))->toBe([]);
})->issue(266);
