<?php

declare(strict_types=1);

namespace Marko\Core\Command;

/**
 * Handles writing output to a stream (console).
 */
class Output
{
    /** @var resource */
    private mixed $stream;

    /**
     * @param resource|null $stream The output stream to write to (defaults to STDOUT)
     */
    public function __construct(
        mixed $stream = null,
    ) {
        $this->stream = $stream ?? STDOUT;
    }

    /**
     * Writes text to the output stream without a trailing newline.
     */
    public function write(
        string $text,
    ): void {
        fwrite($this->stream, $text);
    }

    /**
     * Writes text to the output stream followed by a newline.
     */
    public function writeLine(
        string $text,
    ): void {
        fwrite($this->stream, $text . "\n");
    }
}
