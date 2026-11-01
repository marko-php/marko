<?php

declare(strict_types=1);

namespace Marko\Validation\Contracts;

/**
 * A rule that runs even when the field's value is empty or the key is absent.
 *
 * The validator skips ordinary rules on an empty value. It always runs implicit rules, and
 * when any implicit rule says the field requires a value, it runs the field's other rules on
 * the empty value too, so `required_if:type,video|url` reports both errors once `type` is
 * `video`, and nothing when it is not.
 */
interface ImplicitRuleInterface extends RuleInterface
{
    /**
     * Whether the field must hold a value given the data being validated.
     *
     * `required` always returns true; `required_if` returns whether its condition holds;
     * rules such as `present` or `accepted` that check the value themselves return false.
     *
     * @param string $field The concrete field being validated (e.g. `items.0.url`)
     * @param array<string, mixed> $data All data being validated
     */
    public function requiresValue(
        string $field,
        array $data,
    ): bool;
}
