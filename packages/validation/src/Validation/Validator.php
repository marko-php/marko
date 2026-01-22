<?php

declare(strict_types=1);

namespace Marko\Validation\Validation;

use Marko\Validation\Contracts\RuleInterface;
use Marko\Validation\Contracts\ValidatorInterface;
use Marko\Validation\Exceptions\ValidationException;
use Marko\Validation\Rules\Nullable;
use Marko\Validation\Rules\Required;

class Validator implements ValidatorInterface
{
    public function __construct(
        private readonly RuleParser $parser = new RuleParser(),
    ) {}

    public function validate(
        array $data,
        array $rules,
    ): ValidationErrors {
        $errors = new ValidationErrors();

        foreach ($rules as $field => $fieldRules) {
            $this->validateField($field, $data, $fieldRules, $errors);
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

    private function validateField(
        string $field,
        array $data,
        mixed $fieldRules,
        ValidationErrors $errors,
    ): void {
        $parsedRules = $this->parser->parse($fieldRules);
        $value = $this->getValue($field, $data);

        $isNullable = $this->hasNullableRule($parsedRules);
        $isRequired = $this->hasRequiredRule($parsedRules);

        // If nullable and value is null/empty, skip other rules
        if ($isNullable && $this->isEmpty($value)) {
            return;
        }

        // If not required and value is empty, skip other rules
        if (!$isRequired && $this->isEmpty($value)) {
            return;
        }

        foreach ($parsedRules as $rule) {
            // Skip nullable rule as it's a meta rule
            if ($rule instanceof Nullable) {
                continue;
            }

            if (!$rule->passes($field, $value, $data)) {
                $errors->add($field, $rule->message($field, $value));
            }
        }
    }

    private function getValue(
        string $field,
        array $data,
    ): mixed {
        // Support dot notation for nested arrays
        if (!str_contains($field, '.')) {
            return $data[$field] ?? null;
        }

        $keys = explode('.', $field);
        $value = $data;

        foreach ($keys as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }

    /**
     * @param array<RuleInterface> $rules
     */
    private function hasNullableRule(
        array $rules,
    ): bool {
        foreach ($rules as $rule) {
            if ($rule instanceof Nullable) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<RuleInterface> $rules
     */
    private function hasRequiredRule(
        array $rules,
    ): bool {
        foreach ($rules as $rule) {
            if ($rule instanceof Required) {
                return true;
            }
        }

        return false;
    }

    private function isEmpty(
        mixed $value,
    ): bool {
        if ($value === null) {
            return true;
        }

        if (is_string($value) && trim($value) === '') {
            return true;
        }

        if (is_array($value) && $value === []) {
            return true;
        }

        return false;
    }
}
