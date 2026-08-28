<?php

declare(strict_types=1);

use Marko\Session\Config\SessionConfig;
use Marko\Session\Contracts\SessionHandlerInterface;
use Marko\Session\Exceptions\SessionNotStartedException;
use Marko\Session\Session;
use Marko\Testing\Fake\FakeConfigRepository;

/**
 * Builds the SessionConfig shared by the tests in this file.
 */
function createTestSessionConfig(): SessionConfig
{
    return new SessionConfig(new FakeConfigRepository([
        'session.driver' => 'array',
        'session.lifetime' => 120,
        'session.expire_on_close' => false,
        'session.path' => '/tmp',
        'session.cookie.name' => 'PHPSESSID',
        'session.cookie.path' => '/',
        'session.cookie.domain' => '',
        'session.cookie.secure' => false,
        'session.cookie.httponly' => true,
        'session.cookie.samesite' => 'lax',
        'session.gc_probability' => 1,
        'session.gc_divisor' => 100,
    ]));
}

/**
 * An in-memory SessionHandlerInterface implementation used to drive a real
 * Session without touching the filesystem.
 */
function createInMemorySessionHandler(): SessionHandlerInterface
{
    return new class () implements SessionHandlerInterface
    {
        /** @var array<string, string> */
        public array $written = [];

        public function open(
            string $path,
            string $name,
        ): bool {
            return true;
        }

        public function close(): bool
        {
            return true;
        }

        public function read(string $id): string|false
        {
            return $this->written[$id] ?? '';
        }

        public function write(
            string $id,
            string $data,
        ): bool {
            $this->written[$id] = $data;

            return true;
        }

        public function destroy(string $id): bool
        {
            unset($this->written[$id]);

            return true;
        }

        public function gc(int $max_lifetime): int|false
        {
            return 0;
        }
    };
}

/**
 * Creates a Session with started=true via reflection so PHP session functions
 * are not required to drive the write-after-close behaviour.
 */
function createStartedSession(): Session
{
    $sessionConfig = createTestSessionConfig();
    $session = new Session(createInMemorySessionHandler(), $sessionConfig);

    // Use reflection to set started = true without triggering session_start()
    $reflection = new ReflectionProperty(Session::class, 'started');
    $reflection->setValue($session, true);

    return $session;
}

it('throws a loud session exception when set is called after save', function (): void {
    $session = createStartedSession();

    $session->save();

    expect(fn () => $session->set('key', 'value'))
        ->toThrow(SessionNotStartedException::class);
});

it('persists data written before save', function (): void {
    $session = createStartedSession();

    $session->set('key', 'value');

    expect($session->get('key'))->toBe('value');
});

it('disables sapi cookie emission when the session starts', function (): void {
    $session = new Session(createInMemorySessionHandler(), createTestSessionConfig());

    $session->start();

    try {
        expect(ini_get('session.use_cookies'))->toBe('0');
    } finally {
        $session->save();
    }
});

it('clears the session id when reset', function (): void {
    $session = createStartedSession();
    $idProperty = new ReflectionProperty(Session::class, 'id');
    $idProperty->setValue($session, 'abcdefghijklmnopqrstuvwxyz012345');

    $session->reset();

    expect($session->getId())->toBe('');
});

it('clears the session data when reset', function (): void {
    $session = createStartedSession();
    $session->set('key', 'value');

    $session->reset();

    $dataProperty = new ReflectionProperty(Session::class, 'data');

    expect($dataProperty->getValue($session))->toBeEmpty();
});

it('starts a fresh session when a second request arrives with no cookie', function (): void {
    $session = new Session(createInMemorySessionHandler(), createTestSessionConfig());

    // Request 1: an authenticated visitor
    $session->start();
    $firstRequestId = $session->getId();
    $session->set('user_id', 42);
    $session->save();

    // Worker resets state between requests
    $session->reset();

    // Request 2: an anonymous visitor with no session cookie — nothing calls setId()
    $session->start();

    try {
        expect($session->getId())->not->toBe($firstRequestId)
            ->and($session->get('user_id'))->toBeNull();
    } finally {
        $session->save();
    }
});

it('resumes the same session when a second request arrives with the same cookie', function (): void {
    $session = new Session(createInMemorySessionHandler(), createTestSessionConfig());

    // Request 1: a visitor sets data and receives a session cookie
    $session->start();
    $firstRequestId = $session->getId();
    $session->set('user_id', 42);
    $session->save();

    // Worker resets state between requests
    $session->reset();

    // Request 2: the same visitor returns with the session cookie from request 1
    $session->setId($firstRequestId);
    $session->start();

    try {
        expect($session->getId())->toBe($firstRequestId)
            ->and($session->get('user_id'))->toBe(42);
    } finally {
        $session->save();
    }
});
