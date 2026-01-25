<?php

declare(strict_types=1);

namespace Marko\Validation\Rules;

use Marko\Validation\Contracts\RuleInterface;

class In implements RuleInterface
{
    /**
     * @var array
     */
    private array $values;

    public function __construct(
        mixed ...$values,
    ) {
        $this->values = $values;
    }

    public function passes(
        string $field,
        mixed $value,
        array $data,
    ): bool {
        if ($value === null || $value === '') {
            return true;
        }

        return in_array($value, $this->values, true);
    }

    public function message(
        string $field,
        mixed $value,
    ): string {
        $allowed = implode(', ', array_map(
            static fn ($v) => is_string($v) ? "'$v'" : (string) $v,
            $this->values,
        ));

        return "The $field field must be one of: $allowed.";
    }
}
