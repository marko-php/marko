<?php

declare(strict_types=1);

namespace Marko\Validation\Rules;

use Marko\Core\Contracts\UploadedFileInterface;
use Marko\Validation\Contracts\RuleInterface;

/**
 * Exactly the given size: an int or float by value, an array by item count, a string by
 * length --- or by value in numeric mode (see AbstractSizeRule).
 */
readonly class Size extends AbstractSizeRule
{
    public function __construct(
        private int|float $size,
        bool $numeric = false,
    ) {
        parent::__construct($numeric);
    }

    public function asNumeric(): RuleInterface
    {
        return new self($this->size, true);
    }

    public function passes(
        string $field,
        mixed $value,
        array $data,
    ): bool {
        if ($value === null || $value === '') {
            return true;
        }

        // Files are sized with max_size/min_size; message() says so.
        if ($value instanceof UploadedFileInterface) {
            return false;
        }

        $size = $this->size($value);

        return $size !== null && (float) $size === (float) $this->size;
    }

    public function message(
        string $field,
        mixed $value,
    ): string {
        if ($value instanceof UploadedFileInterface) {
            return "The $field field is a file: use min_size:$this->size|max_size:$this->size to require its size in kilobytes.";
        }

        if (is_array($value)) {
            return "The $field field must have exactly $this->size items.";
        }

        if ($this->measuresLength($value)) {
            return "The $field field must be exactly $this->size characters.";
        }

        return "The $field field must be $this->size.";
    }
}
