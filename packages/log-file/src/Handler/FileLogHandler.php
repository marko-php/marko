<?php

declare(strict_types=1);

namespace Marko\Log\File\Handler;

use Marko\Log\Contracts\LogFormatterInterface;
use Marko\Log\Contracts\LogHandlerInterface;
use Marko\Log\Exceptions\LogWriteException;
use Marko\Log\File\Rotation\RotationStrategyInterface;
use Marko\Log\LogRecord;

/**
 * Appends each record to a per-channel file in the log directory, named by the rotation strategy.
 * New log files get $fileMode and a newly created log directory gets $dirMode (owner-only by default).
 */
readonly class FileLogHandler implements LogHandlerInterface
{
    public function __construct(
        private string $path,
        private LogFormatterInterface $formatter,
        private RotationStrategyInterface $rotation,
        private int $fileMode = 0600,
        private int $dirMode = 0700,
    ) {}

    /**
     * @throws LogWriteException
     */
    public function handle(
        LogRecord $record,
    ): void {
        $this->ensureDirectoryExists();

        $filePath = $this->rotation->getCurrentPath($this->path, $record->channel);
        $line = $this->formatter->format($record);

        $this->ensureFileExists($filePath);

        $result = file_put_contents($filePath, $line, FILE_APPEND | LOCK_EX);

        if ($result === false) {
            throw LogWriteException::forPath($filePath);
        }
    }

    /**
     * The file is created empty and restricted before any record is written, so log content is never
     * readable under the process umask's default (typically world-readable 0644) mode.
     *
     * @throws LogWriteException
     */
    private function ensureFileExists(
        string $filePath,
    ): void {
        if (is_file($filePath)) {
            return;
        }

        if (!touch($filePath)) {
            throw LogWriteException::forPath($filePath, 'could not create the log file');
        }

        if (!chmod($filePath, $this->fileMode)) {
            throw LogWriteException::forPath($filePath, sprintf('could not set mode 0%o', $this->fileMode));
        }
    }

    /**
     * @throws LogWriteException
     */
    private function ensureDirectoryExists(): void
    {
        if (is_dir($this->path)) {
            if (!is_writable($this->path)) {
                throw LogWriteException::directoryNotWritable($this->path);
            }

            return;
        }

        if (!mkdir($this->path, $this->dirMode, true) && !is_dir($this->path)) {
            throw LogWriteException::directoryNotWritable($this->path);
        }

        // mkdir() is filtered by the umask; chmod applies the configured mode exactly.
        if (!chmod($this->path, $this->dirMode)) {
            throw LogWriteException::directoryNotWritable($this->path);
        }
    }
}
