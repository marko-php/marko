<?php

declare(strict_types=1);

namespace Marko\Routing\File;

use Generator;
use Marko\Routing\Exceptions\FileResponseException;

/**
 * Reads a byte range of an open stream resource in fixed-size chunks, then closes it.
 *
 * Shared by the file sources so each one only has to open its stream. A
 * stream that cannot seek (some remote stream wrappers) is skipped forward
 * by reading and discarding up to the offset.
 */
class ResourceChunkReader
{
    private const int CHUNK_SIZE = 65536;

    /**
     * @param resource $handle Closed when the generator finishes or is destroyed
     * @return Generator<int, string>
     * @throws FileResponseException When a read fails
     */
    public function read(
        mixed $handle,
        int $offset = 0,
        ?int $length = null,
    ): Generator {
        try {
            $this->seek($handle, $offset);

            $remaining = $length;

            while ($remaining === null || $remaining > 0) {
                $chunk = fread($handle, $remaining === null ? self::CHUNK_SIZE : min(self::CHUNK_SIZE, $remaining));

                if ($chunk === false) {
                    throw FileResponseException::readFailed();
                }

                if ($chunk === '') {
                    break;
                }

                if ($remaining !== null) {
                    $remaining -= strlen($chunk);
                }

                yield $chunk;
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param resource $handle
     * @throws FileResponseException
     */
    private function seek(
        mixed $handle,
        int $offset,
    ): void {
        if ($offset === 0) {
            return;
        }

        if (stream_get_meta_data($handle)['seekable'] && fseek($handle, $offset) === 0) {
            return;
        }

        $toSkip = $offset;

        while ($toSkip > 0 && !feof($handle)) {
            $skipped = fread($handle, min(self::CHUNK_SIZE, $toSkip));

            if ($skipped === false) {
                throw FileResponseException::readFailed();
            }

            $toSkip -= strlen($skipped);
        }
    }
}
