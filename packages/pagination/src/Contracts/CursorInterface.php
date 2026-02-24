<?php

declare(strict_types=1);

namespace Marko\Pagination\Contracts;

use Marko\Pagination\Exceptions\PaginationException;

interface CursorInterface
{
    /**
     * Get all cursor parameters.
     *
     * @return array<string, mixed>
     */
    public function parameters(): array;

    /**
     * Get a specific cursor parameter by name.
     */
    public function parameter(string $name): mixed;

    /**
     * Encode the cursor to a URL-safe string.
     */
    public function encode(): string;

    /**
     * Decode a cursor string back to a CursorInterface instance.
     *
     * @throws PaginationException
     */
    public static function decode(string $encoded): static;
}
