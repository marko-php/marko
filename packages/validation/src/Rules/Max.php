<?php

declare(strict_types=1);

namespace Marko\Validation\Rules;

use Marko\Core\Contracts\UploadedFileInterface;
use Marko\Validation\Contracts\RuleInterface;

readonly class Max extends AbstractSizeRule
{
    public function __construct(
        private int|float $maximum,
        bool $numeric = false,
    ) {
        parent::__construct($numeric);
    }

    public function asNumeric(): RuleInterface
    {
        return new self($this->maximum, true);
    }

    public function passes(
        string $field,
        mixed $value,
        array $data,
    ): bool {
        if (
            $value === null
            || $value === ''
        ) {
            return true;
        }

        // Files are sized with max_size/min_size; message() says so.
        if ($value instanceof UploadedFileInterface) {
            return false;
        }

        $size = $this->size($value);

        return $size !== null
            && $size <= $this->maximum;
    }

    public function message(
        string $field,
        mixed $value,
    ): string {
        if ($value instanceof UploadedFileInterface) {
            return "The $field field is a file: use max_size:$this->maximum to limit its size in kilobytes.";
        }

        if (is_array($value)) {
            return "The $field field must not have more than $this->maximum items.";
        }

        if ($this->measuresLength($value)) {
            return "The $field field must not exceed $this->maximum characters.";
        }

        return "The $field field must not exceed $this->maximum.";
    }
}
