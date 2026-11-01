<?php

declare(strict_types=1);

namespace Marko\Routing\Tests\Http\Support;

use Marko\Routing\Http\OutputStreamer;
use Marko\Routing\Http\Response;
use Marko\Routing\Http\StreamedResponseInterface;

/**
 * Runs a response's output inside an output buffer, with an OutputStreamer that does not drain it.
 */
class StreamCapture
{
    public static function streamer(
        bool $aborted = false,
    ): OutputStreamer {
        return new class ($aborted) extends OutputStreamer
        {
            public function __construct(
                private readonly bool $aborted,
            ) {}

            public function prepare(): void {}

            public function connectionAborted(): bool
            {
                return $this->aborted;
            }
        };
    }

    public static function body(
        StreamedResponseInterface $response,
    ): string {
        return self::capture(fn () => $response->withOutputStreamer(self::streamer())->sendBody());
    }

    public static function send(
        Response $response,
    ): string {
        if ($response instanceof StreamedResponseInterface) {
            $response = $response->withOutputStreamer(self::streamer());
        }

        return self::capture(fn () => $response->send());
    }

    private static function capture(
        callable $callback,
    ): string {
        ob_start();

        try {
            $callback();
        } finally {
            $output = (string) ob_get_clean();
        }

        return $output;
    }
}
