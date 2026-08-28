<?php

declare(strict_types=1);

use Marko\Session\Config\SessionConfig;
use Marko\Session\Contracts\SessionHandlerInterface;
use Marko\Session\Session;
use Marko\Testing\Fake\FakeConfigRepository;

it('registers the session save handler shutdown function only once', function (): void {
    $GLOBALS['sessionSetSaveHandlerCallCount'] = 0;

    $sessionConfig = new SessionConfig(new FakeConfigRepository([
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

    $session = new Session($handler, $sessionConfig);

    // Request 1
    $session->start();
    $session->save();
    $session->reset();

    // Request 2, in the same long-running process
    $session->start();
    $session->save();

    expect($GLOBALS['sessionSetSaveHandlerCallCount'])->toBe(1);
});
