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
 * Base for `required_with` and `required_without`: the field is required depending on which
 * of the other fields are filled (present and not empty). Each rule supplies only how that
 * decides the requirement and how it reads in the message.
 *
 * @phpstan-consistent-constructor
 */
abstract readonly class AbstractRequiredWithRule implements ImplicitRuleInterface, WildcardAwareRuleInterface
{
    /**
     * @var list<FieldReference>
     */
    private array $otherFields;

    /**
     * @param string ...$otherFields Absolute dot paths; a `*` is replaced by the current row's index
     *
     * @throws InvalidArgumentException When no field is given, or one is blank
     */
    public function __construct(
        string ...$otherFields,
    ) {
        if ($otherFields === [] || array_any($otherFields, static fn (string $path): bool => trim($path) === '')) {
            throw new InvalidArgumentException(
                "The {$this->name()} rule needs at least one field, e.g. {$this->name()}:email",
            );
        }

        $this->otherFields = array_values(array_map(
            fn (string $path): FieldReference => new FieldReference($this->name(), $path),
            $otherFields,
        ));
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
        $otherFields = implode(' / ', array_map(
            static fn (FieldReference $reference): string => $reference->path,
            $this->otherFields,
        ));

        return "The $field field is required when $otherFields {$this->relation()}.";
    }

    /**
     * @throws InvalidArgumentException
     */
    public function forWildcardIndexes(
        string $key,
        array $indexes,
    ): RuleInterface {
        return new static(...array_map(
            static fn (FieldReference $reference): string => $reference->forWildcardIndexes($key, $indexes)->path,
            $this->otherFields,
        ));
    }

    /**
     * Whether each other field is filled, in the order they were listed.
     *
     * @param array<mixed> $data
     *
     * @return list<bool>
     */
    protected function filled(
        array $data,
    ): array {
        return array_map(
            static fn (FieldReference $reference): bool => $reference->isFilled($data),
            $this->otherFields,
        );
    }

    /**
     * The rule's string key, e.g. `required_with`.
     */
    abstract protected function name(): string;

    /**
     * How the other fields relate to the requirement, for the message, e.g. `is present`.
     */
    abstract protected function relation(): string;
}
