<?php

declare(strict_types=1);

namespace Marko\Validation\Contracts;

use Marko\Validation\Validation\ValidationErrors;

/**
 * Reads the form state a failed submission flashed for the next request: the
 * validation errors and the old input to refill the form with.
 *
 * This is the single reader of flashed form state. View helpers, Inertia and
 * test assertions all read through it, so none of them knows how or where the
 * state is stored. Reading never consumes the state. marko/forms provides the
 * implementation.
 */
interface FlashedFormStateInterface
{
    /**
     * The flashed validation errors; empty when nothing was flashed.
     */
    public function errors(): ValidationErrors;

    /**
     * Whether any validation errors were flashed.
     */
    public function hasErrors(): bool;

    /**
     * The flashed input at a dot path (`email`, `address.city`, `items.0.sku`),
     * or $default when it was not flashed.
     */
    public function old(
        string $key,
        mixed $default = null,
    ): mixed;
}
