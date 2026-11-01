<?php

declare(strict_types=1);

use Marko\Core\Path\ProjectPaths;
use Marko\Session\Config\SessionConfig;
use Marko\Session\Contracts\SessionHandlerInterface;
use Marko\Session\File\Exceptions\InsecureSessionPathException;
use Marko\Session\File\Exceptions\SessionWriteException;
use Marko\Session\File\Handler\FileSessionHandler;
use Marko\Testing\Fake\FakeClock;
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
        'session.lifetime' => 60,
    ]);

    return new SessionConfig($configRepo);
}

beforeEach(function (): void {
    $this->sessionPath = getSessionTestPath();
    $this->clock = new FakeClock('2026-01-01 12:00:00 UTC');
    $this->handler = new FileSessionHandler(
        createSessionConfig($this->sessionPath),
        $this->clock,
        new ProjectPaths(sys_get_temp_dir()),
    );
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

it('uses a UNC session path as given instead of prefixing the project root', function (): void {
    // On a non-Windows host the UNC path is a plain relative directory name, so
    // using it as given lands in the cwd rather than under the project root.
    $workDir = getSessionTestPath();
    mkdir($workDir);
    $uncPath = '\\\\server\\share\\sessions';
    $handler = new FileSessionHandler(
        createSessionConfig($uncPath),
        $this->clock,
        new ProjectPaths('/nonexistent-project-root'),
    );
    $originalCwd = getcwd();
    chdir($workDir);

    try {
        $handler->open($uncPath, 'PHPSESSID');
    } finally {
        chdir($originalCwd);
    }

    expect(is_dir($workDir . '/' . $uncPath))->toBeTrue();

    rmdir($workDir . '/' . $uncPath);
    rmdir($workDir);
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
    touch($sessionFile, $this->clock->now()->getTimestamp() - 3700);

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

it('garbage collects only files older than max lifetime relative to the clock', function (): void {
    $this->handler->open($this->sessionPath, 'PHPSESSID');
    $this->handler->write('aging-session', 'data');
    $sessionFile = $this->sessionPath . '/sess_aging-session';
    touch($sessionFile, $this->clock->now()->getTimestamp() - 100);

    $countBefore = $this->handler->gc(3600);
    $this->clock->travel('+1 hour');
    $countAfter = $this->handler->gc(3600);

    expect($countBefore)->toBe(0)
        ->and($countAfter)->toBe(1)
        ->and(file_exists($sessionFile))->toBeFalse();
});

it('keeps files exactly at the max lifetime boundary', function (): void {
    $this->handler->open($this->sessionPath, 'PHPSESSID');
    $this->handler->write('boundary-session', 'data');
    $this->handler->write('expired-session', 'data');
    $now = $this->clock->now()->getTimestamp();
    touch($this->sessionPath . '/sess_boundary-session', $now - 3600);
    touch($this->sessionPath . '/sess_expired-session', $now - 3601);

    $count = $this->handler->gc(3600);

    expect($count)->toBe(1)
        ->and(file_exists($this->sessionPath . '/sess_boundary-session'))->toBeTrue()
        ->and(file_exists($this->sessionPath . '/sess_expired-session'))->toBeFalse();
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
        $handler = new FileSessionHandler(
            createSessionConfig('partial-write://session-dir'),
            new FakeClock(),
            new ProjectPaths(sys_get_temp_dir()),
        );
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
        $handler = new FileSessionHandler(
            createSessionConfig('fail-truncate://session-dir'),
            new FakeClock(),
            new ProjectPaths(sys_get_temp_dir()),
        );
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

describe('strict session ids', function (): void {
    it('validates an id whose session file exists within the lifetime', function (): void {
        $this->handler->open($this->sessionPath, 'PHPSESSID');
        $this->handler->write('live-session', 'data');
        touch($this->sessionPath . '/sess_live-session', $this->clock->now()->getTimestamp() - 3600);

        expect($this->handler->validateId('live-session'))->toBeTrue();
    });

    it('rejects an id with no session file', function (): void {
        $this->handler->open($this->sessionPath, 'PHPSESSID');

        expect($this->handler->validateId('never-issued'))->toBeFalse();
    });

    it('rejects an id whose session file is older than the lifetime', function (): void {
        $this->handler->open($this->sessionPath, 'PHPSESSID');
        $this->handler->write('stale-session', 'data');
        touch($this->sessionPath . '/sess_stale-session', $this->clock->now()->getTimestamp() - 3601);

        expect($this->handler->validateId('stale-session'))->toBeFalse();
    });

    it('rejects an id once the clock moves past the lifetime', function (): void {
        $this->handler->open($this->sessionPath, 'PHPSESSID');
        $this->handler->write('aging-session', 'data');
        touch($this->sessionPath . '/sess_aging-session', $this->clock->now()->getTimestamp());

        $before = $this->handler->validateId('aging-session');
        $this->clock->travel('+61 minutes');

        expect($before)->toBeTrue()
            ->and($this->handler->validateId('aging-session'))->toBeFalse();
    });

    it('updates the session file timestamp without changing its payload', function (): void {
        $this->handler->open($this->sessionPath, 'PHPSESSID');
        $this->handler->write('touched-session', 'payload');
        $file = $this->sessionPath . '/sess_touched-session';
        touch($file, $this->clock->now()->getTimestamp() - 1800);

        $result = $this->handler->updateTimestamp('touched-session', 'ignored-data');
        clearstatcache(true, $file);

        expect($result)->toBeTrue()
            ->and(filemtime($file))->toBe($this->clock->now()->getTimestamp())
            ->and(file_get_contents($file))->toBe('payload');
    });

    it('returns true when updating the timestamp of an id whose file no longer exists', function (): void {
        $this->handler->open($this->sessionPath, 'PHPSESSID');

        expect($this->handler->updateTimestamp('gone-session', ''))->toBeTrue();
    });

    it('stamps the session file mtime with the clock on write', function (): void {
        $this->handler->open($this->sessionPath, 'PHPSESSID');

        $this->handler->write('stamped-session', 'data');
        $file = $this->sessionPath . '/sess_stamped-session';
        clearstatcache(true, $file);

        expect(filemtime($file))->toBe($this->clock->now()->getTimestamp());
    });

    it('sees a fresh mtime after the file changes within the same process', function (): void {
        $this->handler->open($this->sessionPath, 'PHPSESSID');
        $this->handler->write('restat-session', 'data');
        $file = $this->sessionPath . '/sess_restat-session';
        $before = $this->handler->validateId('restat-session');

        // Age the file behind PHP's stat cache, as another worker process would.
        exec('touch -t 200001010000 ' . escapeshellarg($file));

        expect($before)->toBeTrue()
            ->and($this->handler->validateId('restat-session'))->toBeFalse();
    });

    it('does not create a session file when updating the timestamp of an unknown id', function (): void {
        $this->handler->open($this->sessionPath, 'PHPSESSID');

        $this->handler->updateTimestamp('never-issued', '');

        expect(file_exists($this->sessionPath . '/sess_never-issued'))->toBeFalse();
    });
});

describe('session path resolution', function (): void {
    beforeEach(function (): void {
        $this->projectBase = getSessionTestPath();
        mkdir($this->projectBase . '/public', 0755, true);
        $this->originalCwd = getcwd();
    });

    afterEach(function (): void {
        chdir($this->originalCwd);
        exec('rm -rf ' . escapeshellarg($this->projectBase));
    });

    it('resolves a relative path against the project base instead of the working directory', function (): void {
        // FPM, CGI and mod_php chdir into public/ before running the front controller.
        chdir($this->projectBase . '/public');

        $handler = new FileSessionHandler(
            createSessionConfig('storage/sessions'),
            new FakeClock(),
            new ProjectPaths($this->projectBase),
        );
        $handler->open('', 'PHPSESSID');
        $handler->write('resolved-session', 'data');

        expect(is_file($this->projectBase . '/storage/sessions/sess_resolved-session'))->toBeTrue()
            ->and(is_dir($this->projectBase . '/public/storage'))->toBeFalse();
    });

    it('leaves an absolute path untouched', function (): void {
        $absolute = $this->projectBase . '/elsewhere/sessions';

        $handler = new FileSessionHandler(
            createSessionConfig($absolute),
            new FakeClock(),
            new ProjectPaths('/some/other/base'),
        );
        $handler->open('', 'PHPSESSID');
        $handler->write('absolute-session', 'data');

        expect(is_file($absolute . '/sess_absolute-session'))->toBeTrue();
    });

    it('refuses a relative path that resolves inside the public directory', function (): void {
        new FileSessionHandler(
            createSessionConfig('public/storage/sessions'),
            new FakeClock(),
            new ProjectPaths($this->projectBase),
        );
    })->throws(InsecureSessionPathException::class);

    it('refuses an absolute path inside the public directory', function (): void {
        new FileSessionHandler(
            createSessionConfig($this->projectBase . '/public/sessions'),
            new FakeClock(),
            new ProjectPaths($this->projectBase),
        );
    })->throws(InsecureSessionPathException::class);

    it('refuses the public directory itself', function (): void {
        new FileSessionHandler(
            createSessionConfig('public'),
            new FakeClock(),
            new ProjectPaths($this->projectBase),
        );
    })->throws(InsecureSessionPathException::class);

    it('refuses a path that reaches the public directory through dot segments', function (): void {
        new FileSessionHandler(
            createSessionConfig('storage/../public/./sessions'),
            new FakeClock(),
            new ProjectPaths($this->projectBase),
        );
    })->throws(InsecureSessionPathException::class);

    it('allows a sibling directory whose name merely starts with public', function (): void {
        $handler = new FileSessionHandler(
            createSessionConfig('public-sessions'),
            new FakeClock(),
            new ProjectPaths($this->projectBase),
        );
        $handler->open('', 'PHPSESSID');

        expect(is_dir($this->projectBase . '/public-sessions'))->toBeTrue();
    });

    it('explains which path was refused and how to fix it', function (): void {
        try {
            new FileSessionHandler(
                createSessionConfig('public/storage/sessions'),
                new FakeClock(),
                new ProjectPaths($this->projectBase),
            );
        } catch (InsecureSessionPathException $e) {
            expect($e->getMessage())->toContain('public')
                ->and($e->getContext())->toContain($this->projectBase . '/public/storage/sessions')
                ->and($e->getSuggestion())->toContain('session.path');

            return;
        }

        throw new RuntimeException('Expected InsecureSessionPathException');
    });
});
