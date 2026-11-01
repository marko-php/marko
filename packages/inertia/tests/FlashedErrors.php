<?php

declare(strict_types=1);

namespace Marko\Inertia\Tests;

use Marko\Validation\Contracts\FlashedFormStateInterface;
use Marko\Validation\Validation\ValidationErrors;

/**
 * Flashed form state holding fixed validation errors, standing in for the
 * reader marko/forms binds.
 */
readonly class FlashedErrors implements FlashedFormStateInterface
{
    /**
     * @param array<string, array<string>> $errors
     */
    public function __construct(
        private array $errors = [],
    ) {}

    public function errors(): ValidationErrors
    {
        return new ValidationErrors($this->errors);
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    public function old(
        string $key,
        mixed $default = null,
    ): mixed {
        return $default;
    }
}
