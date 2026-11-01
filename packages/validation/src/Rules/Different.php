<?php

declare(strict_types=1);

namespace Marko\Validation\Rules;

use InvalidArgumentException;
use Marko\Validation\Contracts\RuleInterface;
use Marko\Validation\Contracts\WildcardAwareRuleInterface;
use Marko\Validation\Validation\FieldReference;

readonly class Different implements WildcardAwareRuleInterface
{
    private FieldReference $otherField;

    /**
     * @param string $otherField An absolute dot path; a `*` is replaced by the current row's index
     */
    public function __construct(
        string $otherField,
    ) {
        $this->otherField = new FieldReference('different', $otherField);
    }

    public function passes(
        string $field,
        mixed $value,
        array $data,
    ): bool {
        if ($value === null || $value === '') {
            return true;
        }

        return $value !== $this->otherField->get($data);
    }

    public function message(
        string $field,
        mixed $value,
    ): string {
        return "The $field field must be different from the {$this->otherField->path} field.";
    }

    /**
     * @throws InvalidArgumentException
     */
    public function forWildcardIndexes(
        string $key,
        array $indexes,
    ): RuleInterface {
        return new self($this->otherField->forWildcardIndexes($key, $indexes)->path);
    }
}
