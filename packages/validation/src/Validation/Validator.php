<?php

declare(strict_types=1);

namespace Marko\Validation\Validation;

use InvalidArgumentException;
use Marko\Validation\Contracts\ImplicitRuleInterface;
use Marko\Validation\Contracts\RuleInterface;
use Marko\Validation\Contracts\ValidatorInterface;
use Marko\Validation\Contracts\WildcardAwareRuleInterface;
use Marko\Validation\Exceptions\ValidationException;
use Marko\Validation\Rules\Nullable;
use Marko\Validation\Rules\Sometimes;

readonly class Validator implements ValidatorInterface
{
    public function __construct(
        private RuleParser $parser = new RuleParser(),
    ) {}

    /**
     * A rules key containing `*` is expanded against the data, so errors are keyed by the
     * concrete path (`photos.1`, `items.0.name`).
     *
     * @throws InvalidArgumentException When a rule string is invalid or a cross-field rule has more wildcards than its key
     */
    public function validate(
        array $data,
        array $rules,
    ): ValidationErrors {
        $errors = new ValidationErrors();

        foreach ($rules as $key => $fieldRules) {
            $key = (string) $key;
            $parsedRules = $this->parser->parse($fieldRules);
            $this->assertWildcardsResolvable($key, $parsedRules);

            if (!str_contains($key, '*')) {
                $this->validateField($key, $key, [], $data, $parsedRules, $errors);

                continue;
            }

            foreach ($this->expand($key, explode('.', $key), $data, '', [], $errors) as [$field, $indexes]) {
                $this->validateField($key, $field, $indexes, $data, $parsedRules, $errors);
            }
        }

        return $errors;
    }

    public function validateOrFail(
        array $data,
        array $rules,
    ): void {
        $errors = $this->validate($data, $rules);

        if ($errors->isNotEmpty()) {
            throw ValidationException::withErrors($errors);
        }
    }

    public function passes(
        array $data,
        array $rules,
    ): bool {
        return $this->validate($data, $rules)->isEmpty();
    }

    public function fails(
        array $data,
        array $rules,
    ): bool {
        return !$this->passes($data, $rules);
    }

    /**
     * @param string $key The rules key, which may contain wildcards
     * @param string $field The concrete field being validated
     * @param list<string> $indexes The array keys the key's wildcards matched, in order
     * @param array<RuleInterface> $parsedRules
     *
     * @throws InvalidArgumentException
     */
    private function validateField(
        string $key,
        string $field,
        array $indexes,
        array $data,
        array $parsedRules,
        ValidationErrors $errors,
    ): void {
        $parsedRules = array_map(
            static fn (RuleInterface $rule): RuleInterface => $rule instanceof WildcardAwareRuleInterface
                ? $rule->forWildcardIndexes($key, $indexes)
                : $rule,
            $parsedRules,
        );

        // A field marked sometimes is validated only when its key is present
        if ($this->hasRule($parsedRules, Sometimes::class) && !DataPath::has($data, $field)) {
            return;
        }

        $value = DataPath::get($data, $field);
        $isEmpty = EmptyValue::is($value);

        // If nullable and value is null/empty, skip every rule
        if ($isEmpty && $this->hasRule($parsedRules, Nullable::class)) {
            return;
        }

        // An empty value runs only the implicit rules, unless one of them requires a value
        $runsAllRules = !$isEmpty || array_any(
            $parsedRules,
            static fn (RuleInterface $rule): bool => $rule instanceof ImplicitRuleInterface
                && $rule->requiresValue($field, $data),
        );

        foreach ($parsedRules as $rule) {
            // Skip the meta rules
            if ($rule instanceof Nullable || $rule instanceof Sometimes) {
                continue;
            }

            if (!$runsAllRules && !$rule instanceof ImplicitRuleInterface) {
                continue;
            }

            if (!$rule->passes($field, $value, $data)) {
                $errors->add($field, $rule->message($field, $value));
            }
        }
    }

    /**
     * Expand a rules key such as `items.*.name` into the concrete fields present in the data
     * (`items.0.name`, `items.1.name`, ...), each with the indexes its wildcards matched.
     * A `*` segment is always a wildcard. Over an absent or empty value it expands to nothing;
     * over any other non-array value it records an error under the path reached, so a rule
     * the data cannot reach never passes silently.
     *
     * @param list<string> $segments The key's segments still to walk
     * @param list<string> $indexes The indexes matched so far
     *
     * @return list<array{string, list<string>}>
     */
    private function expand(
        string $key,
        array $segments,
        mixed $value,
        string $prefix,
        array $indexes,
        ValidationErrors $errors,
    ): array {
        if ($segments === []) {
            return [[$prefix, $indexes]];
        }

        $segment = array_shift($segments);

        if ($segment !== '*') {
            $next = is_array($value) && array_key_exists($segment, $value) ? $value[$segment] : null;

            return $this->expand($key, $segments, $next, $this->join($prefix, $segment), $indexes, $errors);
        }

        if (EmptyValue::is($value)) {
            return [];
        }

        if (!is_array($value)) {
            $errors->add($prefix, "The $prefix field must be an array to apply the $key rules.");

            return [];
        }

        $fields = [];

        foreach (array_keys($value) as $index) {
            $index = (string) $index;
            $fields = [
                ...$fields,
                ...$this->expand(
                    $key,
                    $segments,
                    $value[$index],
                    $this->join($prefix, $index),
                    [...$indexes, $index],
                    $errors,
                ),
            ];
        }

        return $fields;
    }

    /**
     * Check every cross-field rule against the key's wildcard count up front, so a reference
     * with more wildcards than the key fails even when the data has no rows to expand.
     *
     * @param array<RuleInterface> $parsedRules
     *
     * @throws InvalidArgumentException
     */
    private function assertWildcardsResolvable(
        string $key,
        array $parsedRules,
    ): void {
        $wildcards = array_fill(0, count(array_keys(explode('.', $key), '*', true)), '*');

        foreach ($parsedRules as $rule) {
            if ($rule instanceof WildcardAwareRuleInterface) {
                $rule->forWildcardIndexes($key, $wildcards);
            }
        }
    }

    private function join(
        string $prefix,
        string $segment,
    ): string {
        return $prefix === '' ? $segment : "$prefix.$segment";
    }

    /**
     * @param array<RuleInterface> $rules
     * @param class-string<RuleInterface> $class
     */
    private function hasRule(
        array $rules,
        string $class,
    ): bool {
        return array_any($rules, static fn (RuleInterface $rule): bool => $rule instanceof $class);
    }
}
