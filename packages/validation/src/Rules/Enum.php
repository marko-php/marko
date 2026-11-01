<?php

declare(strict_types=1);

namespace Marko\Validation\Rules;

use BackedEnum;
use InvalidArgumentException;
use Marko\Validation\Contracts\RuleInterface;
use ReflectionEnum;
use ReflectionNamedType;

/**
 * A case of a backed enum, given as the case itself or its backing value.
 *
 * An int-backed enum also accepts an integer string (`"2"`), since form input is always a
 * string. There is no `enum:` rule string: build it with Enum::of(Status::class).
 */
readonly class Enum implements RuleInterface
{
    /**
     * @var list<BackedEnum>
     */
    private array $cases;

    private bool $intBacked;

    /**
     * @param class-string<BackedEnum> $enum
     * @param ?list<BackedEnum> $cases The allowed cases; null allows every case
     *
     * @throws InvalidArgumentException When $enum is not a backed enum or a case is not one of its cases
     */
    public function __construct(
        private string $enum,
        ?array $cases = null,
    ) {
        if (!enum_exists($enum)) {
            throw new InvalidArgumentException("Enum::of() needs a backed enum, but $enum is not an enum");
        }

        if (!is_subclass_of($enum, BackedEnum::class)) {
            throw new InvalidArgumentException(
                "Enum::of() needs a backed enum, but $enum is a pure enum, so its cases have no value to validate. "
                . "Give it a backing type: enum Name: string { case A = 'a'; }",
            );
        }

        $this->assertCasesOf($cases ?? []);
        $this->cases = $cases ?? $enum::cases();
        $type = new ReflectionEnum($enum)->getBackingType();
        $this->intBacked = $type instanceof ReflectionNamedType && $type->getName() === 'int';
    }

    /**
     * @param class-string<BackedEnum> $enum
     *
     * @throws InvalidArgumentException When $enum is not a backed enum
     */
    public static function of(
        string $enum,
    ): self {
        return new self($enum);
    }

    /**
     * Allow only the given cases (of those this rule already allows).
     *
     * @throws InvalidArgumentException When a case is not one of the enum's cases
     */
    public function only(
        BackedEnum ...$cases,
    ): self {
        $this->assertCasesOf($cases);

        return new self(
            $this->enum,
            array_values(
                array_filter($this->cases, static fn (BackedEnum $case): bool => in_array($case, $cases, true)),
            ),
        );
    }

    /**
     * Allow every case this rule already allows except the given ones.
     *
     * @throws InvalidArgumentException When a case is not one of the enum's cases
     */
    public function except(
        BackedEnum ...$cases,
    ): self {
        $this->assertCasesOf($cases);

        return new self(
            $this->enum,
            array_values(
                array_filter($this->cases, static fn (BackedEnum $case): bool => !in_array($case, $cases, true)),
            ),
        );
    }

    public function passes(
        string $field,
        mixed $value,
        array $data,
    ): bool {
        if ($value === null || $value === '') {
            return true;
        }

        $case = $this->resolve($value);

        return $case !== null && in_array($case, $this->cases, true);
    }

    public function message(
        string $field,
        mixed $value,
    ): string {
        $allowed = implode(', ', array_map(
            static fn (BackedEnum $case): string => is_string($case->value) ? "'$case->value'" : (string) $case->value,
            $this->cases,
        ));

        return "The $field field must be one of: $allowed.";
    }

    private function resolve(
        mixed $value,
    ): ?BackedEnum {
        if ($value instanceof $this->enum) {
            return $value;
        }

        $enum = $this->enum;

        if ($this->intBacked) {
            if (is_string($value) && preg_match('/^-?[0-9]+$/D', $value) === 1) {
                $value = (int) $value;
            }

            return is_int($value) ? $enum::tryFrom($value) : null;
        }

        if (is_int($value)) {
            $value = (string) $value;
        }

        return is_string($value) ? $enum::tryFrom($value) : null;
    }

    /**
     * @param array<BackedEnum> $cases
     *
     * @throws InvalidArgumentException
     */
    private function assertCasesOf(
        array $cases,
    ): void {
        foreach ($cases as $case) {
            if (!$case instanceof $this->enum) {
                $name = $case::class . '::' . $case->name;

                throw new InvalidArgumentException("$name is not a case of $this->enum");
            }
        }
    }
}
