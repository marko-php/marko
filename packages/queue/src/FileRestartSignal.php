<?php

declare(strict_types=1);

namespace Marko\Queue;

use Marko\Core\Path\ProjectPaths;
use Marko\Core\Support\ErrorCapture;
use Marko\Queue\Exceptions\QueueException;

/**
 * Restart signal kept in the file named by queue.restart_signal.
 *
 * A relative path is resolved against the project root. Every worker that should restart must see the
 * same file, so this suits workers on one host or on hosts that share the filesystem; bind another
 * RestartSignalInterface implementation for hosts that share nothing.
 */
class FileRestartSignal implements RestartSignalInterface
{
    private readonly string $path;

    public function __construct(
        QueueConfig $config,
        ProjectPaths $paths,
    ) {
        $path = $config->restartSignal();

        // Resolve against the project root, never getcwd(), which depends on where the command was started
        $this->path = str_starts_with($path, '/') ? $path : $paths->base . '/' . $path;
    }

    /**
     * Write a new random token to a temp file beside the signal file, then rename it over the signal
     * file, so a worker never reads a half-written token.
     *
     * @throws QueueException When the directory cannot be created or the file cannot be written
     */
    public function request(): void
    {
        $this->ensureDirectoryExists();

        $temp = $this->path . '.' . bin2hex(random_bytes(6)) . '.tmp';
        $token = bin2hex(random_bytes(16));

        if (ErrorCapture::run($reason, fn (): int|false => file_put_contents($temp, $token)) !== strlen($token)) {
            $this->removeTemp($temp);

            throw QueueException::restartSignalNotWritable($this->path, $reason ?? 'The temp file was not written.');
        }

        if (!ErrorCapture::run($reason, fn (): bool => rename($temp, $this->path))) {
            $this->removeTemp($temp);

            throw QueueException::restartSignalNotWritable($this->path, $reason ?? 'The temp file was not renamed.');
        }
    }

    /**
     * @throws QueueException When the signal file exists but cannot be read
     */
    public function current(): ?string
    {
        if (!is_file($this->path)) {
            return null;
        }

        $token = ErrorCapture::run($reason, fn (): string|false => file_get_contents($this->path));

        if ($token === false) {
            throw QueueException::restartSignalNotReadable($this->path, $reason ?? 'The file could not be read.');
        }

        $token = trim($token);

        return $token === '' ? null : $token;
    }

    /**
     * @throws QueueException
     */
    private function ensureDirectoryExists(): void
    {
        $directory = dirname($this->path);

        if (is_dir($directory)) {
            return;
        }

        // A concurrent creator winning the race is fine: mkdir() fails but the directory then exists
        if (!ErrorCapture::run($reason, fn (): bool => mkdir($directory, 0755, recursive: true))
            && !is_dir($directory)
        ) {
            throw QueueException::restartSignalNotWritable(
                $this->path,
                $reason ?? "The directory '$directory' could not be created.",
            );
        }
    }

    private function removeTemp(
        string $temp,
    ): void {
        if (is_file($temp)) {
            unlink($temp);
        }
    }
}
