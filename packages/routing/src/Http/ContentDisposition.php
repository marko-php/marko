<?php

declare(strict_types=1);

namespace Marko\Routing\Http;

use Marko\Routing\Exceptions\ContentDispositionException;

/**
 * The Content-Disposition header: inline or attachment, with an optional filename.
 *
 * toHeaderValue() gives the RFC 6266 form. A non-ASCII filename is sent twice:
 * an ASCII fallback in `filename` (other characters become `_`) and the exact
 * name in `filename*=UTF-8''...`. fromHeader() reads either form back,
 * preferring `filename*`.
 */
readonly class ContentDisposition
{
    private const string PARAMETER_PATTERN = '/;\s*(filename\*?)\s*=\s*("(?:[^"\\\\]|\\\\.)*"|[^;]*)/i';

    private const string EXTENDED_VALUE_PATTERN = "/^UTF-8'[^']*'(.*)$/i";

    /**
     * @throws ContentDispositionException When the filename is empty or holds CR, LF, NUL or a path separator
     */
    private function __construct(
        private DispositionType $type,
        private ?string $filename,
    ) {
        if ($filename !== null && ($filename === '' || preg_match('#[\r\n\0/\\\\]#', $filename) === 1)) {
            throw ContentDispositionException::invalidFilename($filename);
        }
    }

    /**
     * @throws ContentDispositionException
     */
    public static function inline(
        ?string $filename = null,
    ): self {
        return new self(type: DispositionType::Inline, filename: $filename);
    }

    /**
     * @throws ContentDispositionException
     */
    public static function attachment(
        ?string $filename = null,
    ): self {
        return new self(type: DispositionType::Attachment, filename: $filename);
    }

    /**
     * Parse a Content-Disposition header value.
     *
     * @throws ContentDispositionException When the type is not inline/attachment or the filename is invalid
     */
    public static function fromHeader(
        string $value,
    ): self {
        $typeToken = strtolower(trim(explode(';', $value, 2)[0]));
        $type = DispositionType::tryFrom($typeToken)
            ?? throw ContentDispositionException::malformedHeader($value);

        preg_match_all(self::PARAMETER_PATTERN, $value, $matches, PREG_SET_ORDER);

        $filename = null;
        $extendedFilename = null;

        foreach ($matches as [, $name, $rawValue]) {
            $rawValue = trim($rawValue);

            if (strtolower($name) === 'filename*') {
                if (preg_match(self::EXTENDED_VALUE_PATTERN, $rawValue, $extended) !== 1) {
                    throw ContentDispositionException::malformedHeader($value);
                }

                $extendedFilename = rawurldecode($extended[1]);
                continue;
            }

            $filename = str_starts_with($rawValue, '"')
                ? (string) preg_replace('/\\\\(.)/s', '$1', substr($rawValue, 1, -1))
                : $rawValue;
        }

        return new self(type: $type, filename: $extendedFilename ?? $filename);
    }

    public function type(): DispositionType
    {
        return $this->type;
    }

    public function filename(): ?string
    {
        return $this->filename;
    }

    public function toHeaderValue(): string
    {
        if ($this->filename === null) {
            return $this->type->value;
        }

        // Per character for valid UTF-8, per byte otherwise.
        $fallback = (string) (preg_replace('/[^\x20-\x7E]/u', '_', $this->filename)
            ?? preg_replace('/[^\x20-\x7E]/', '_', $this->filename));
        $header = $this->type->value . '; filename="' . addcslashes($fallback, '"') . '"';

        if ($fallback !== $this->filename) {
            $header .= "; filename*=UTF-8''" . rawurlencode($this->filename);
        }

        return $header;
    }
}
