<?php

declare(strict_types=1);

namespace Marko\Pagination;

use Marko\Pagination\Contracts\CursorInterface;
use Marko\Pagination\Exceptions\PaginationException;

readonly class Cursor implements CursorInterface
{
    /**
     * @param array<string, mixed> $params
     */
    public function __construct(
        private array $params,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function parameters(): array
    {
        return $this->params;
    }

    public function parameter(
        string $name,
    ): mixed {
        return $this->params[$name] ?? null;
    }

    public function encode(): string
    {
        return base64_encode(json_encode($this->params, JSON_THROW_ON_ERROR));
    }

    public static function decode(
        string $encoded,
    ): static {
        if ($encoded === '') {
            throw PaginationException::invalidCursor($encoded);
        }

        $decoded = base64_decode($encoded, true);

        if ($decoded === false) {
            throw PaginationException::invalidCursor($encoded);
        }

        $data = json_decode($decoded, true);

        if (!is_array($data)) {
            throw PaginationException::invalidCursor($encoded);
        }

        return new static($data);
    }
}
