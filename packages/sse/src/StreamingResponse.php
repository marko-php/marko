<?php

declare(strict_types=1);

namespace Marko\Sse;

use JsonException;
use Marko\Routing\Http\Response;
use Override;

readonly class StreamingResponse extends Response
{
    public function __construct(
        private SseStream $stream,
        int $statusCode = 200,
    ) {
        parent::__construct(
            body: '',
            statusCode: $statusCode,
            headers: [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'Connection' => 'keep-alive',
                'X-Accel-Buffering' => 'no',
            ],
        );
    }

    /**
     * @throws JsonException
     */
    #[Override]
    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode());

            foreach ($this->headers() as $name => $value) {
                header("$name: $value");
            }
        }

        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        ob_implicit_flush(true);
        set_time_limit(0);

        try {
            foreach ($this->stream as $chunk) {
                if (connection_aborted()) {
                    break;
                }

                echo $chunk;
                flush();
            }
        } finally {
            $this->stream->close();
        }
    }
}
