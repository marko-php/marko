<?php

declare(strict_types=1);

namespace Marko\Validation\Rules;

use InvalidArgumentException;
use Marko\Validation\Contracts\ImplicitRuleInterface;
use Marko\Validation\Contracts\RuleInterface;
use Marko\Validation\Contracts\WildcardAwareRuleInterface;
use Marko\Validation\Validation\EmptyValue;
use Marko\Validation\Validation\FieldReference;

/**
 * Base for `required_if` and `required_unless`: the field is required depending on whether
 * another field holds one of the listed values. Each rule supplies only how the match
 * decides the requirement and how it reads in the message.
 *
 * Values are compared as strings: `true`/`false` and `null` read as those words, numbers as
 * their digits, and a boolean field also matches `1`/`0`. An array never matches.
 *
 * @phpstan-consistent-constructor
 */
abstract readonly class AbstractRequiredIfRule implements ImplicitRuleInterface, WildcardAwareRuleInterface
{
    private FieldReference $otherField;

    /**
     * @var list<string>
     */
    private array $values;

    /**
     * @param string $otherField An absolute dot path; a `*` is replaced by the current row's index
     *
     * @throws InvalidArgumentException When the field or the values are missing
     */
    public function __construct(
        string $otherField,
        string|int|float|bool|null ...$values,
    ) {
        if (trim($otherField) === '' || $values === []) {
            throw new InvalidArgumentException(
                "The {$this->name()} rule needs a field and at least one value, e.g. {$this->name()}:type,video",
            );
        }

        $this->otherField = new FieldReference($this->name(), $otherField);
        $this->values = array_values(array_map(self::normalize(...), $values));
    }

    public function passes(
        string $field,
        mixed $value,
        array $data,
    ): bool {
        return !$this->requiresValue($field, $data) || !EmptyValue::is($value);
    }

    public function message(
        string $field,
        mixed $value,
    ): string {
        return "The $field field is required "
            . $this->describe($this->otherField->path, implode(', ', $this->values)) . '.';
    }

    /**
     * @throws InvalidArgumentException
     */
    public function forWildcardIndexes(
        string $key,
        array $indexes,
    ): RuleInterface {
        return new static($this->otherField->forWildcardIndexes($key, $indexes)->path, ...$this->values);
    }

    /**
     * Whether the other field holds one of the listed values.
     *
     * @param array<mixed> $data
     */
    protected function matches(
        array $data,
    ): bool {
        $actual = $this->otherField->get($data);

        if (is_bool($actual)) {
            $accepted = $actual ? ['true', '1'] : ['false', '0'];

            return array_any($this->values, static fn (string $value): bool => in_array($value, $accepted, true));
        }

        if ($actual !== null && !is_scalar($actual)) {
            return false;
        }

        return in_array(self::normalize($actual), $this->values, true);
    }

    /**
     * The rule's string key, e.g. `required_if`.
     */
    abstract protected function name(): string;

    /**
     * The condition for the message, e.g. `when type is video, audio`.
     */
    abstract protected function describe(
        string $otherField,
        string $values,
    ): string;

    private static function normalize(
        string|int|float|bool|null $value,
    ): string {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            default => (string) $value,
        };
    }
}
