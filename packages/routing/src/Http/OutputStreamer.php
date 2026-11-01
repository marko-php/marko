<?php

declare(strict_types=1);

namespace Marko\Routing\Http;

/**
 * Writes a streamed body to the client as it is produced.
 *
 * prepare() drains every output buffer and lifts the execution time limit so
 * chunks are not held back; write() echoes and flushes one chunk, and stops
 * (returns false) once the client has disconnected.
 */
class OutputStreamer
{
    public function prepare(): void
    {
        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        ob_implicit_flush(true);
        set_time_limit(0);
    }

    /**
     * @return bool false when the client has gone away and the chunk was not written; stop producing output
     */
    public function write(
        string $chunk,
    ): bool {
        if ($this->connectionAborted()) {
            return false;
        }

        echo $chunk;
        flush();

        return true;
    }

    public function connectionAborted(): bool
    {
        return connection_aborted() === 1;
    }
}
