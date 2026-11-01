<?php

declare(strict_types=1);

namespace Marko\Forms\View;

use ArrayAccess;
use Marko\Forms\Exceptions\FormsException;
use Marko\Validation\Contracts\FlashedFormStateInterface;

/**
 * The $old template variable: read-only array access to the flashed input.
 *
 *     Latte: {$old['email']}  {$old['address.city']}
 *     Twig:  {{ old.email }}  {{ old.address.city }}
 *
 * A missing field reads as null.
 *
 * @implements ArrayAccess<string, mixed>
 */
readonly class OldInput implements ArrayAccess
{
    public function __construct(
        private FlashedFormStateInterface $formState,
    ) {}

    public function offsetExists(
        mixed $offset,
    ): bool {
        return $this->formState->old((string) $offset) !== null;
    }

    public function offsetGet(
        mixed $offset,
    ): mixed {
        return $this->formState->old((string) $offset);
    }

    /**
     * @throws FormsException
     */
    public function offsetSet(
        mixed $offset,
        mixed $value,
    ): void {
        throw FormsException::readOnlyOldInput();
    }

    /**
     * @throws FormsException
     */
    public function offsetUnset(
        mixed $offset,
    ): void {
        throw FormsException::readOnlyOldInput();
    }
}
