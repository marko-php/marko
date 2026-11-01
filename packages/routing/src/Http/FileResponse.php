<?php

declare(strict_types=1);

namespace Marko\Routing\Http;

use Marko\Routing\Exceptions\FileResponseException;
use Marko\Routing\Exceptions\RangeNotSatisfiableException;
use Marko\Routing\File\FileSourceInterface;
use Marko\Routing\File\LocalFileSource;
use NoDiscard;

/**
 * Serves a file without loading it into memory, with HTTP Range and conditional request support.
 *
 * fromSource() answers the request as it stands:
 * - 304 Not Modified when If-None-Match / If-Modified-Since show the client's copy is current;
 * - 206 Partial Content for a single satisfiable `Range` on a GET (unless If-Range no longer matches);
 * - 416 (RangeNotSatisfiableException) for a malformed or out-of-bounds range;
 * - 200 with the whole file otherwise, including multi-range requests.
 *
 * With a FileOffload and a LocalFileSource, the web server sends the file
 * instead (X-Sendfile / X-Accel-Redirect) and handles Range itself; the
 * response carries the offload header and no body.
 */
class FileResponse extends AbstractStreamedResponse
{
    /**
     * @param array<string, string> $headers
     */
    private function __construct(
        private FileSourceInterface $fileSource,
        private ?ByteRange $byteRange,
        private bool $hasBody,
        int $statusCode,
        array $headers,
    ) {
        parent::__construct(statusCode: $statusCode, headers: $headers);
    }

    /**
     * @throws RangeNotSatisfiableException|FileResponseException
     */
    public static function fromSource(
        Request $request,
        FileSourceInterface $fileSource,
        ?FileOffload $fileOffload = null,
    ): self {
        $lastModified = $fileSource->lastModified();
        $baseHeaders = [
            'Content-Type' => $fileSource->mimeType(),
            'Last-Modified' => ConditionalRequest::httpDate($lastModified),
        ];

        if ($fileOffload !== null && $fileSource instanceof LocalFileSource) {
            return new self(
                fileSource: $fileSource,
                byteRange: null,
                hasBody: false,
                statusCode: 200,
                headers: [
                    ...$baseHeaders,
                    $fileOffload->header() => $fileOffload->headerValueFor($fileSource->path()),
                ],
            );
        }

        $size = $fileSource->size();
        $etag = ConditionalRequest::etagFor($size, $lastModified);
        $headers = [...$baseHeaders, 'ETag' => $etag, 'Accept-Ranges' => 'bytes'];
        $conditionalRequest = ConditionalRequest::fromRequest($request);
        $method = $request->method();

        if (($method === 'GET' || $method === 'HEAD') && $conditionalRequest->isNotModified($etag, $lastModified)) {
            return new self(
                fileSource: $fileSource,
                byteRange: null,
                hasBody: false,
                statusCode: 304,
                headers: ['ETag' => $etag, 'Last-Modified' => $baseHeaders['Last-Modified']],
            );
        }

        $byteRange = $method === 'GET' && $conditionalRequest->allowsRange($etag, $lastModified)
            ? ByteRange::fromHeader($request->header('Range'), $size)
            : null;

        if ($byteRange !== null) {
            return new self(
                fileSource: $fileSource,
                byteRange: $byteRange,
                hasBody: true,
                statusCode: 206,
                headers: [
                    ...$headers,
                    'Content-Length' => (string) $byteRange->length(),
                    'Content-Range' => $byteRange->contentRange($size),
                ],
            );
        }

        return new self(
            fileSource: $fileSource,
            byteRange: null,
            hasBody: true,
            statusCode: 200,
            headers: [...$headers, 'Content-Length' => (string) $size],
        );
    }

    /**
     * Set Content-Disposition, e.g. `ContentDisposition::attachment('report.pdf')` to force a download.
     */
    #[NoDiscard]
    public function withDisposition(
        ContentDisposition $contentDisposition,
    ): static {
        return $this->withHeader('Content-Disposition', $contentDisposition->toHeaderValue());
    }

    public function fileSource(): FileSourceInterface
    {
        return $this->fileSource;
    }

    public function byteRange(): ?ByteRange
    {
        return $this->byteRange;
    }

    public function sendBody(): void
    {
        if (!$this->hasBody) {
            return;
        }

        $outputStreamer = $this->outputStreamer();
        $outputStreamer->prepare();

        $chunks = $this->byteRange === null
            ? $this->fileSource->stream()
            : $this->fileSource->stream($this->byteRange->start(), $this->byteRange->length());

        foreach ($chunks as $chunk) {
            if (!$outputStreamer->write($chunk)) {
                break;
            }
        }
    }
}
