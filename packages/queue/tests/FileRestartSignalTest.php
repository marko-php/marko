<?php

declare(strict_types=1);

use Marko\Core\Path\ProjectPaths;
use Marko\Queue\Exceptions\QueueException;
use Marko\Queue\FileRestartSignal;
use Marko\Queue\QueueConfig;
use Marko\Queue\RestartSignalInterface;
use Marko\Testing\Fake\FakeConfigRepository;

function restartSignalRoot(): string
{
    $root = sys_get_temp_dir() . '/marko-restart-signal-' . bin2hex(random_bytes(6));
    mkdir($root);

    return $root;
}

function removeRestartSignalRoot(
    string $path,
): void {
    if (!is_dir($path)) {
        if (file_exists($path)) {
            unlink($path);
        }

        return;
    }

    foreach (array_diff((array) scandir($path), ['.', '..']) as $entry) {
        removeRestartSignalRoot($path . '/' . $entry);
    }

    rmdir($path);
}

function createFileRestartSignal(
    string $root,
    string $path = 'storage/queue/restart',
): FileRestartSignal {
    return new FileRestartSignal(
        new QueueConfig(new FakeConfigRepository(['queue.restart_signal' => $path])),
        new ProjectPaths($root),
    );
}

describe('FileRestartSignal', function (): void {
    beforeEach(function (): void {
        $this->root = restartSignalRoot();
    });

    afterEach(function (): void {
        removeRestartSignalRoot($this->root);
    });

    it('implements RestartSignalInterface', function (): void {
        expect(createFileRestartSignal($this->root))->toBeInstanceOf(RestartSignalInterface::class);
    });

    it('returns null before any restart was requested', function (): void {
        expect(createFileRestartSignal($this->root)->current())->toBeNull();
    });

    it('records a new token on every request', function (): void {
        $signal = createFileRestartSignal($this->root);

        $signal->request();
        $first = $signal->current();
        $signal->request();
        $second = $signal->current();

        expect($first)->toBeString()->not->toBe('')
            ->and($second)->toBeString()->not->toBe('')
            ->and($second)->not->toBe($first);
    });

    it('resolves a relative path against the project root and creates missing directories', function (): void {
        createFileRestartSignal($this->root, 'storage/queue/restart')->request();

        expect(is_file($this->root . '/storage/queue/restart'))->toBeTrue();
    });

    it('uses an absolute path as given', function (): void {
        $absolute = $this->root . '/elsewhere/restart';

        createFileRestartSignal('/nonexistent-project-root', $absolute)->request();

        expect(is_file($absolute))->toBeTrue();
    });

    it('shares the token between instances that point at the same file', function (): void {
        $deploy = createFileRestartSignal($this->root);
        $worker = createFileRestartSignal($this->root);

        $deploy->request();

        expect($worker->current())->toBe($deploy->current());
    });

    it('writes atomically through a temp file renamed over the signal file', function (): void {
        $signal = createFileRestartSignal($this->root);
        $file = $this->root . '/storage/queue/restart';

        $signal->request();
        $inodeBefore = fileinode($file);
        $signal->request();

        // A rename replaces the file with a new inode; writing in place would keep the old one
        expect(fileinode($file))->not->toBe($inodeBefore)
            ->and(array_values(array_diff((array) scandir(dirname($file)), ['.', '..'])))->toBe(['restart']);
    });

    it('throws a QueueException when the signal directory cannot be created', function (): void {
        file_put_contents($this->root . '/storage', 'not a directory');

        expect(fn () => createFileRestartSignal($this->root)->request())
            ->toThrow(QueueException::class, 'Could not write the queue restart signal');
    });
});
