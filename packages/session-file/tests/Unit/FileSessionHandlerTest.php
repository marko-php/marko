<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Session\Config\SessionConfig;
use Marko\Session\Contracts\SessionHandlerInterface;
use Marko\Session\File\Handler\FileSessionHandler;

function getSessionTestPath(): string
{
    return sys_get_temp_dir() . '/marko-session-test-' . uniqid();
}

function cleanupSessionTestPath(
    string $path,
): void {
    if (!is_dir($path)) {
        return;
    }

    $files = glob($path . '/*');
    if ($files !== false) {
        foreach ($files as $file) {
            unlink($file);
        }
    }
    rmdir($path);
}

function createSessionConfig(
    string $path,
): SessionConfig {
    $configRepo = new readonly class ($path) implements ConfigRepositoryInterface
    {
        public function __construct(private string $path) {}

        public function get(
            string $key,
            ?string $scope = null,
        ): mixed {
            if ($key === 'session.path') {
                return $this->path;
            }
            throw new ConfigNotFoundException($key);
        }

        public function getString(
            string $key,
            ?string $scope = null,
        ): string {
            return (string) $this->get($key, $scope);
        }

        public function getInt(
            string $key,
            ?string $scope = null,
        ): int {
            return (int) $this->get($key, $scope);
        }

        public function getBool(
            string $key,
            ?string $scope = null,
        ): bool {
            return (bool) $this->get($key, $scope);
        }

        public function getFloat(
            string $key,
            ?string $scope = null,
        ): float {
            return (float) $this->get($key, $scope);
        }

        public function getArray(
            string $key,
            ?string $scope = null,
        ): array {
            return (array) $this->get($key, $scope);
        }

        public function has(
            string $key,
            ?string $scope = null,
        ): bool {
            return $key === 'session.path';
        }

        public function all(
            ?string $scope = null,
        ): array {
            return [];
        }

        public function withScope(
            string $scope,
        ): ConfigRepositoryInterface {
            return $this;
        }
    };

    return new SessionConfig($configRepo);
}

beforeEach(function () {
    $this->sessionPath = getSessionTestPath();
    $this->handler = new FileSessionHandler(createSessionConfig($this->sessionPath));
});

afterEach(function () {
    cleanupSessionTestPath($this->sessionPath);
});

it('implements SessionHandlerInterface', function () {
    expect($this->handler)->toBeInstanceOf(SessionHandlerInterface::class);
});

it('creates directory on open', function () {
    $this->handler->open($this->sessionPath, 'PHPSESSID');

    expect(is_dir($this->sessionPath))->toBeTrue();
});

it('returns true on open', function () {
    expect($this->handler->open($this->sessionPath, 'PHPSESSID'))->toBeTrue();
});

it('returns true on close', function () {
    expect($this->handler->close())->toBeTrue();
});

it('returns empty string for missing session', function () {
    $this->handler->open($this->sessionPath, 'PHPSESSID');

    expect($this->handler->read('nonexistent'))->toBe('');
});

it('writes and reads session data', function () {
    $this->handler->open($this->sessionPath, 'PHPSESSID');

    $this->handler->write('test-session-id', 'session_data');

    expect($this->handler->read('test-session-id'))->toBe('session_data');
});

it('writes session file with correct prefix', function () {
    $this->handler->open($this->sessionPath, 'PHPSESSID');

    $this->handler->write('abc123', 'data');

    expect(file_exists($this->sessionPath . '/sess_abc123'))->toBeTrue();
});

it('destroys session file', function () {
    $this->handler->open($this->sessionPath, 'PHPSESSID');
    $this->handler->write('to-delete', 'data');

    $result = $this->handler->destroy('to-delete');

    expect($result)->toBeTrue()
        ->and(file_exists($this->sessionPath . '/sess_to-delete'))->toBeFalse();
});

it('returns true when destroying nonexistent session', function () {
    $this->handler->open($this->sessionPath, 'PHPSESSID');

    expect($this->handler->destroy('nonexistent'))->toBeTrue();
});

it('garbage collects expired sessions', function () {
    $this->handler->open($this->sessionPath, 'PHPSESSID');

    // Create a session file
    $this->handler->write('old-session', 'data');
    $sessionFile = $this->sessionPath . '/sess_old-session';

    // Set modification time to past
    touch($sessionFile, time() - 3700);

    $count = $this->handler->gc(3600);

    expect($count)->toBe(1)
        ->and(file_exists($sessionFile))->toBeFalse();
});

it('does not garbage collect active sessions', function () {
    $this->handler->open($this->sessionPath, 'PHPSESSID');

    $this->handler->write('active-session', 'data');

    $count = $this->handler->gc(3600);

    expect($count)->toBe(0)
        ->and(file_exists($this->sessionPath . '/sess_active-session'))->toBeTrue();
});

it('handles concurrent reads', function () {
    $this->handler->open($this->sessionPath, 'PHPSESSID');
    $this->handler->write('concurrent', 'original-data');

    $data1 = $this->handler->read('concurrent');
    $data2 = $this->handler->read('concurrent');

    expect($data1)->toBe('original-data')
        ->and($data2)->toBe('original-data');
});

it('handles overwriting session data', function () {
    $this->handler->open($this->sessionPath, 'PHPSESSID');

    $this->handler->write('overwrite', 'first');
    $this->handler->write('overwrite', 'second');

    expect($this->handler->read('overwrite'))->toBe('second');
});

it('handles empty session data', function () {
    $this->handler->open($this->sessionPath, 'PHPSESSID');

    $this->handler->write('empty', '');

    expect($this->handler->read('empty'))->toBe('');
});

it('returns true from write', function () {
    $this->handler->open($this->sessionPath, 'PHPSESSID');

    expect($this->handler->write('test', 'data'))->toBeTrue();
});
