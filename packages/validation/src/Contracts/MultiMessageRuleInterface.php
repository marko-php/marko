<?php

declare(strict_types=1);

namespace Marko\Validation\Contracts;

/**
 * A rule that checks several requirements and reports each failed one as its own error.
 *
 * When such a rule fails, the validator adds every string from messages() to the field's
 * errors instead of the single message(). message() should still return a combined message
 * for callers that use the rule on its own.
 */
interface MultiMessageRuleInterface extends RuleInterface
{
    /**
     * One message per failed requirement, in the order the requirements are checked.
     *
     * @return list<string>
     */
    public function messages(
        string $field,
        mixed $value,
    ): array;
}
