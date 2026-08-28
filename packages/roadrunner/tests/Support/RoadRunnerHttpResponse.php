<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Tests\Support;

/**
 * One real HTTP response received from a real `rr serve` process, carrying
 * just enough of the raw `Set-Cookie` header for a test to reuse the
 * server-issued session cookie on a follow-up request.
 */
readonly class RoadRunnerHttpResponse
{
    public function __construct(
        public int $statusCode,
        public string $body,
        public ?string $setCookie,
    ) {}

    /**
     * The `name=value` pair from `Set-Cookie`, stripped of attributes
     * (`Path`, `HttpOnly`, ...) so it is directly usable as the value of a
     * `Cookie` request header on the next request.
     */
    public function cookiePair(): ?string
    {
        if ($this->setCookie === null) {
            return null;
        }

        return explode(';', $this->setCookie, 2)[0];
    }
}
