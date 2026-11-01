<?php

declare(strict_types=1);

namespace Marko\Session\Contracts;

use Marko\Session\Exceptions\InvalidFlashDataException;

/**
 * Structured data that lives for exactly the next request.
 *
 * A value put() on one request is readable on the next request and gone on the
 * one after, whether or not anything read it. Reads never consume a value, so
 * it can be read any number of times. Use FlashBag for "kept until displayed"
 * string messages; use this for data such as validation errors and old input.
 */
interface FlashDataInterface
{
    /**
     * Store a value for the next request only. It is not visible on the
     * current request.
     *
     * @param mixed $value null, a scalar, or an array of those (nested arrays allowed)
     *
     * @throws InvalidFlashDataException When the value holds a closure or any other object
     */
    public function put(
        string $key,
        mixed $value,
    ): void;

    /**
     * The value the previous request flashed under $key, or $default when it flashed none.
     * Reading does not consume the value.
     */
    public function get(
        string $key,
        mixed $default = null,
    ): mixed;

    /**
     * Whether the previous request flashed a value under $key (even null).
     */
    public function has(
        string $key,
    ): bool;

    /**
     * Carry values the previous request flashed over to the next request as well.
     * With no keys, every value is kept. Keys that were not flashed are ignored.
     */
    public function keep(
        string ...$keys,
    ): void;
}
