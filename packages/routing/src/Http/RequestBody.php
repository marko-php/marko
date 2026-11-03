<?php

declare(strict_types=1);

namespace Marko\Routing\Http;

use Closure;
use JsonException;
use Marko\Routing\Exceptions\RequestBodyTooLargeException;

/**
 * The raw request body, read on first access rather than when the request is built.
 *
 * A request whose body is never read (a form post, a multipart upload, a GET) never copies
 * it into memory, and a body larger than the configured limit throws a 413 instead of
 * exhausting memory_limit.
 */
class RequestBody
{
    private ?string $contents = null;

    /**
     * @var array{0: mixed, 1: ?string}|null the decoded JSON payload and the decoder error, if any
     */
    private ?array $decodedJson = null;

    /**
     * @param Closure(): string $reader reads the whole body; called at most once
     */
    public function __construct(
        private readonly Closure $reader,
        private readonly int $declaredLength = 0,
        private readonly int $maxLength = 0,
    ) {}

    public static function fromString(
        string $contents,
    ): self {
        return new self(static fn (): string => $contents, strlen($contents));
    }

    /**
     * A body read from a stream (such as `php://input`) the first time it is accessed.
     *
     * @param int $declaredLength the length the client declared (`Content-Length`), 0 when unknown
     * @param int $maxLength the largest body that may be read, 0 for no limit
     */
    public static function fromStream(
        string $uri,
        int $declaredLength = 0,
        int $maxLength = 0,
    ): self {
        return new self(
            static fn (): string => (string) file_get_contents($uri),
            $declaredLength,
            $maxLength,
        );
    }

    /**
     * The body of the current PHP request. For a POST larger than `post_max_size`, PHP has
     * already discarded `$_POST` and `$_FILES`, so the body is never read: accessing it throws
     * {@see RequestBodyTooLargeException}.
     *
     * @param array<string, mixed> $server
     */
    public static function fromGlobals(
        array $server,
    ): self {
        $method = strtoupper((string) ($server['REQUEST_METHOD'] ?? 'GET'));
        $maxLength = $method === 'POST' ? max(0, ini_parse_quantity((string) ini_get('post_max_size'))) : 0;

        return self::fromStream(
            'php://input',
            max(0, (int) ($server['CONTENT_LENGTH'] ?? 0)),
            $maxLength,
        );
    }

    /**
     * Whether the declared length is over the limit, so the body will not be read.
     */
    public function exceedsLimit(): bool
    {
        return $this->maxLength > 0 && $this->declaredLength > $this->maxLength;
    }

    /**
     * @throws RequestBodyTooLargeException when the declared length exceeds the limit
     */
    public function contents(): string
    {
        if ($this->contents !== null) {
            return $this->contents;
        }

        if ($this->exceedsLimit()) {
            throw RequestBodyTooLargeException::fromLengths($this->declaredLength, $this->maxLength);
        }

        return $this->contents = ($this->reader)();
    }

    /**
     * Decode the body as JSON, once. An empty body decodes to an empty payload.
     *
     * @return array{0: mixed, 1: ?string} the decoded payload and the decoder error, if any
     * @throws RequestBodyTooLargeException
     */
    public function decodeJson(): array
    {
        if ($this->decodedJson !== null) {
            return $this->decodedJson;
        }

        $contents = $this->contents();

        if (trim($contents) === '') {
            return $this->decodedJson = [[], null];
        }

        try {
            return $this->decodedJson = [json_decode($contents, true, flags: JSON_THROW_ON_ERROR), null];
        } catch (JsonException $e) {
            return $this->decodedJson = [[], $e->getMessage()];
        }
    }
}
