<?php

declare(strict_types=1);

namespace Marko\Validation\Rules;

use InvalidArgumentException;
use Marko\Validation\Contracts\RuleInterface;

/**
 * A UUID in its canonical 8-4-4-4-12 hex form, in either case.
 *
 * Without a version any UUID passes (including the nil UUID); with one, the version digit
 * must match and the variant must be RFC 9562 (`8`, `9`, `a` or `b`).
 */
readonly class Uuid implements RuleInterface
{
    /**
     * @throws InvalidArgumentException When the version is outside 1 to 8
     */
    public function __construct(
        private ?int $version = null,
    ) {
        if ($version !== null && ($version < 1 || $version > 8)) {
            throw new InvalidArgumentException("UUID version must be from 1 to 8, $version given");
        }
    }

    public function passes(
        string $field,
        mixed $value,
        array $data,
    ): bool {
        if ($value === null || $value === '') {
            return true;
        }

        if (!is_string($value)) {
            return false;
        }

        $pattern = $this->version === null
            ? '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD'
            : '/^[0-9a-f]{8}-[0-9a-f]{4}-' . $this->version . '[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD';

        return preg_match($pattern, $value) === 1;
    }

    public function message(
        string $field,
        mixed $value,
    ): string {
        if ($this->version !== null) {
            return "The $field field must be a valid version $this->version UUID.";
        }

        return "The $field field must be a valid UUID.";
    }
}
