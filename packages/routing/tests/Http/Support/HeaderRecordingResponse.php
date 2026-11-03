<?php

declare(strict_types=1);

namespace Marko\Routing\Tests\Http\Support;

use Marko\Routing\Http\Response;

/**
 * A Response that records each header() call instead of making it.
 *
 * header() does nothing under the CLI SAPI, and headers_sent() is already true
 * once the test runner has printed, so both seams are replaced here.
 */
class HeaderRecordingResponse extends Response
{
    /**
     * @var list<array{line: string, replace: bool}>
     */
    public array $emitted = [];

    public bool $headersSent = false;

    protected function headersAlreadySent(): bool
    {
        return $this->headersSent;
    }

    protected function emitHeader(
        string $line,
        bool $replace,
    ): void {
        $this->emitted[] = ['line' => $line, 'replace' => $replace];
    }
}
