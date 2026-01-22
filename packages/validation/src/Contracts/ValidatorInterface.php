<?php

declare(strict_types=1);

namespace Marko\Validation\Contracts;

use Marko\Validation\Exceptions\ValidationException;
use Marko\Validation\Validation\ValidationErrors;

interface ValidatorInterface
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, array<RuleInterface|string>> $rules
     */
    public function validate(
        array $data,
        array $rules,
    ): ValidationErrors;

    /**
     * @param array<string, mixed> $data
     * @param array<string, array<RuleInterface|string>> $rules
     *
     * @throws ValidationException
     */
    public function validateOrFail(
        array $data,
        array $rules,
    ): void;

    /**
     * @param array<string, mixed> $data
     * @param array<string, array<RuleInterface|string>> $rules
     */
    public function passes(
        array $data,
        array $rules,
    ): bool;

    /**
     * @param array<string, mixed> $data
     * @param array<string, array<RuleInterface|string>> $rules
     */
    public function fails(
        array $data,
        array $rules,
    ): bool;
}
