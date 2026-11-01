<?php

declare(strict_types=1);

namespace Marko\Log\Stream\Handler;

use Marko\Log\Contracts\LogFormatterInterface;
use Marko\Log\Contracts\LogHandlerInterface;
use Marko\Log\Exceptions\LogWriteException;
use Marko\Log\LogRecord;

/**
 * Writes each formatted record to an open stream (stderr or stdout) with write buffering off, so a
 * record reaches the platform's log collector as soon as it is logged.
 */
readonly class StreamLogHandler implements LogHandlerInterface
{
    /**
     * @param resource $stream An open, writable stream
     * @param string $name The stream's name for error messages, e.g. php://stderr
     * @throws LogWriteException When $stream is not an open stream
     */
    public function __construct(
        private mixed $stream,
        private string $name,
        private LogFormatterInterface $formatter,
    ) {
        if (!is_resource($this->stream) || get_resource_type($this->stream) !== 'stream') {
            throw LogWriteException::forStream($this->name, 'not an open stream resource');
        }

        stream_set_write_buffer($this->stream, 0);
    }

    /**
     * @throws LogWriteException
     */
    public function handle(
        LogRecord $record,
    ): void {
        if (!is_resource($this->stream)) {
            throw LogWriteException::forStream($this->name, 'the stream has been closed');
        }

        $line = $this->formatter->format($record);

        // One fwrite per record; the loop only continues after a partial write to a pipe.
        while ($line !== '') {
            $written = @fwrite($this->stream, $line);

            if ($written === false || $written === 0) {
                throw LogWriteException::forStream(
                    $this->name,
                    error_get_last()['message'] ?? 'the write was refused',
                );
            }

            $line = substr($line, $written);
        }
    }
}
