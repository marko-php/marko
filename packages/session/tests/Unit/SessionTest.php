<?php

declare(strict_types=1);

use Marko\Session\Config\SessionConfig;
use Marko\Session\Contracts\SessionHandlerInterface;
use Marko\Session\Exceptions\SessionNotStartedException;
use Marko\Session\Session;
use Marko\Testing\Fake\FakeConfigRepository;

/**
 * Creates a Session with started=true via reflection so PHP session functions
 * are not required to drive the write-after-close behaviour.
 */
function createStartedSession(): Session
{
    $config = new FakeConfigRepository([
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
    ]);

    $handler = new class () implements SessionHandlerInterface
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

    $sessionConfig = new SessionConfig($config);
    $session = new Session($handler, $sessionConfig);

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
