<?php

declare(strict_types=1);

namespace Marko\Validation\Rules;

use InvalidArgumentException;
use Marko\Validation\Contracts\RuleInterface;
use Marko\Validation\Contracts\WildcardAwareRuleInterface;
use Marko\Validation\Validation\DateComparison;

/**
 * Base for `after`, `before`, `after_or_equal` and `before_or_equal`. The shared
 * DateComparison resolves and parses both sides; each rule supplies only its operator.
 *
 * @phpstan-consistent-constructor
 */
abstract readonly class AbstractDateComparisonRule implements WildcardAwareRuleInterface
{
    private DateComparison $comparison;

    /**
     * @param string $reference A date strtotime() understands, or the dot path of another
     *                          field; a `*` in the path is replaced by the current row's index
     *
     * @throws InvalidArgumentException When the reference is empty
     */
    public function __construct(
        string $reference,
    ) {
        $this->comparison = new DateComparison($this->name(), $reference);
    }

    /**
     * @throws InvalidArgumentException When the reference is neither a field in the data nor a date
     */
    public function passes(
        string $field,
        mixed $value,
        array $data,
    ): bool {
        if ($value === null || $value === '') {
            return true;
        }

        $comparison = $this->comparison->compare($value, $data);

        return $comparison !== null && $this->accepts($comparison);
    }

    public function message(
        string $field,
        mixed $value,
    ): string {
        return "The $field field must be a date {$this->relation()} {$this->comparison->reference}.";
    }

    /**
     * @throws InvalidArgumentException
     */
    public function forWildcardIndexes(
        string $key,
        array $indexes,
    ): RuleInterface {
        return new static($this->comparison->forWildcardIndexes($key, $indexes)->reference);
    }

    /**
     * The rule's string key, e.g. `after_or_equal`.
     */
    abstract protected function name(): string;

    /**
     * How the value must relate to the reference, for the message, e.g. `after or equal to`.
     */
    abstract protected function relation(): string;

    /**
     * Whether the result of comparing the value with the reference (-1, 0 or 1) passes.
     */
    abstract protected function accepts(
        int $comparison,
    ): bool;
}
