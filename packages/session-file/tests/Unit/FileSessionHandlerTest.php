<?php

declare(strict_types=1);

use Marko\Session\Config\SessionConfig;
use Marko\Session\Contracts\SessionHandlerInterface;
use Marko\Session\File\Exceptions\SessionWriteException;
use Marko\Session\File\Handler\FileSessionHandler;
use Marko\Testing\Fake\FakeConfigRepository;

/**
 * Stream wrapper that simulates a partial write (writes fewer bytes than requested).
 *
 * @noinspection PhpUnused - Used via stream_wrapper_register
 */
class PartialWriteStream
{
    /** @noinspection PhpUnused */
    public mixed $context = null;

    public function stream_open(
        string $path,
        string $mode,
        int $options,
        ?string &$opened_path,
    ): bool {
        return true;
    }

    public function stream_write(string $data): false
    {
        // Simulate a failed write — return false so fwrite returns false
        return false;
    }

    public function stream_truncate(int $new_size): bool
    {
        return true;
    }

    public function stream_lock(int $operation): bool
    {
        return true;
    }

    public function stream_stat(): mixed
    {
        return false;
    }

    public function stream_eof(): bool
    {
        return true;
    }

    public function stream_close(): void {}

    public function stream_metadata(
        string $path,
        int $option,
        mixed $value,
    ): bool {
        return true;
    }

    public function mkdir(
        string $path,
        int $mode,
        int $options,
    ): bool {
        return true;
    }

    public function url_stat(
        string $path,
        int $flags,
    ): mixed {
        return false;
    }
}

/**
 * Stream wrapper that simulates ftruncate failure.
 *
 * @noinspection PhpUnused - Used via stream_wrapper_register
 */
class FailTruncateStream
{
    /** @noinspection PhpUnused */
    public mixed $context = null;

    public function stream_open(
        string $path,
        string $mode,
        int $options,
        ?string &$opened_path,
    ): bool {
        return true;
    }

    public function stream_write(string $data): int
    {
        return strlen($data);
    }

    public function stream_truncate(int $new_size): bool
    {
        // Simulate ftruncate failure
        return false;
    }

    public function stream_lock(int $operation): bool
    {
        return true;
    }

    public function stream_stat(): mixed
    {
        return false;
    }

    public function stream_eof(): bool
    {
        return true;
    }

    public function stream_close(): void {}

    public function stream_metadata(
        string $path,
        int $option,
        mixed $value,
    ): bool {
        return true;
    }

    public function mkdir(
        string $path,
        int $mode,
        int $options,
    ): bool {
        return true;
    }

    public function url_stat(
        string $path,
        int $flags,
    ): mixed {
        return false;
    }
}

function getSessionTestPath(): string
{
    return sys_get_temp_dir() . '/marko-session-test-' . bin2hex(random_bytes(8));
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
    $configRepo = new FakeConfigRepository([
        'session.path' => $path,
    ]);

    return new SessionConfig($configRepo);
}

beforeEach(function (): void {
    $this->sessionPath = getSessionTestPath();
    $this->handler = new FileSessionHandler(createSessionConfig($this->sessionPath));
});

afterEach(function (): void {
    cleanupSessionTestPath($this->sessionPath);
});

it('implements SessionHandlerInterface', function (): void {
    expect($this->handler)->toBeInstanceOf(SessionHandlerInterface::class);
});

it('creates directory on open', function (): void {
    $this->handler->open($this->sessionPath, 'PHPSESSID');

    expect(is_dir($this->sessionPath))->toBeTrue();
});

it('returns true on open', function (): void {
    expect($this->handler->open($this->sessionPath, 'PHPSESSID'))->toBeTrue();
});

it('returns true on close', function (): void {
    expect($this->handler->close())->toBeTrue();
});

it('returns empty string for missing session', function (): void {
    $this->handler->open($this->sessionPath, 'PHPSESSID');

    expect($this->handler->read('nonexistent'))->toBe('');
});

it('writes and reads session data', function (): void {
    $this->handler->open($this->sessionPath, 'PHPSESSID');

    $this->handler->write('test-session-id', 'session_data');

    expect($this->handler->read('test-session-id'))->toBe('session_data');
});

it('writes session file with correct prefix', function (): void {
    $this->handler->open($this->sessionPath, 'PHPSESSID');

    $this->handler->write('abc123', 'data');

    expect(file_exists($this->sessionPath . '/sess_abc123'))->toBeTrue();
});

it('destroys session file', function (): void {
    $this->handler->open($this->sessionPath, 'PHPSESSID');
    $this->handler->write('to-delete', 'data');

    $result = $this->handler->destroy('to-delete');

    expect($result)->toBeTrue()
        ->and(file_exists($this->sessionPath . '/sess_to-delete'))->toBeFalse();
});

it('returns true when destroying nonexistent session', function (): void {
    $this->handler->open($this->sessionPath, 'PHPSESSID');

    expect($this->handler->destroy('nonexistent'))->toBeTrue();
});

it('garbage collects expired sessions', function (): void {
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

it('does not garbage collect active sessions', function (): void {
    $this->handler->open($this->sessionPath, 'PHPSESSID');

    $this->handler->write('active-session', 'data');

    $count = $this->handler->gc(3600);

    expect($count)->toBe(0)
        ->and(file_exists($this->sessionPath . '/sess_active-session'))->toBeTrue();
});

it('handles concurrent reads', function (): void {
    $this->handler->open($this->sessionPath, 'PHPSESSID');
    $this->handler->write('concurrent', 'original-data');

    $data1 = $this->handler->read('concurrent');
    $data2 = $this->handler->read('concurrent');

    expect($data1)->toBe('original-data')
        ->and($data2)->toBe('original-data');
});

it('handles overwriting session data', function (): void {
    $this->handler->open($this->sessionPath, 'PHPSESSID');

    $this->handler->write('overwrite', 'first');
    $this->handler->write('overwrite', 'second');

    expect($this->handler->read('overwrite'))->toBe('second');
});

it('handles empty session data', function (): void {
    $this->handler->open($this->sessionPath, 'PHPSESSID');

    $this->handler->write('empty', '');

    expect($this->handler->read('empty'))->toBe('');
});

it('returns true from write', function (): void {
    $this->handler->open($this->sessionPath, 'PHPSESSID');

    expect($this->handler->write('test', 'data'))->toBeTrue();
});

it('throws SessionWriteException when fwrite does not write all bytes', function (): void {
    $this->handler->open($this->sessionPath, 'PHPSESSID');

    // Register a stream wrapper that simulates partial writes
    stream_wrapper_register('partial-write', PartialWriteStream::class);

    try {
        $handler = new FileSessionHandler(createSessionConfig('partial-write://session-dir'));
        expect(fn () => $handler->write('test-id', 'some-data'))
            ->toThrow(SessionWriteException::class);
    } finally {
        stream_wrapper_unregister('partial-write');
    }
});

it('throws SessionWriteException when ftruncate fails', function (): void {
    $this->handler->open($this->sessionPath, 'PHPSESSID');

    stream_wrapper_register('fail-truncate', FailTruncateStream::class);

    try {
        $handler = new FileSessionHandler(createSessionConfig('fail-truncate://session-dir'));
        expect(fn () => $handler->write('test-id', 'some-data'))
            ->toThrow(SessionWriteException::class);
    } finally {
        stream_wrapper_unregister('fail-truncate');
    }
});

it('leaves an existing session file at 0600 after a subsequent rewrite', function (): void {
    $this->handler->open($this->sessionPath, 'PHPSESSID');

    // Write once and verify permissions
    $this->handler->write('rewrite-test', 'first-data');
    $file = $this->sessionPath . '/sess_rewrite-test';
    expect(fileperms($file) & 0777)->toBe(0600);

    // Rewrite and verify permissions remain 0600
    $this->handler->write('rewrite-test', 'second-data');

    expect(fileperms($file) & 0777)->toBe(0600);
});

it('still reads back exactly what was written for a normal write', function (): void {
    $this->handler->open($this->sessionPath, 'PHPSESSID');

    $data = 'user|a:1:{s:4:"name";s:5:"Alice";}';
    $this->handler->write('normal-write', $data);

    expect($this->handler->read('normal-write'))->toBe($data);
});

it('creates the session directory with 0700 permissions on open', function (): void {
    $this->handler->open($this->sessionPath, 'PHPSESSID');

    expect(fileperms($this->sessionPath) & 0777)->toBe(0700);
});

it('creates a session file with 0600 permissions after write', function (): void {
    $this->handler->open($this->sessionPath, 'PHPSESSID');
    $this->handler->write('perm-test', 'data');

    $file = $this->sessionPath . '/sess_perm-test';

    expect(fileperms($file) & 0777)->toBe(0600);
});
