<?php

declare(strict_types=1);

namespace Marko\Validation\Validation;

use InvalidArgumentException;
use Marko\Validation\Contracts\RuleInterface;
use Marko\Validation\Rules\Alpha;
use Marko\Validation\Rules\AlphaNumeric;
use Marko\Validation\Rules\ArrayType;
use Marko\Validation\Rules\Between;
use Marko\Validation\Rules\Boolean;
use Marko\Validation\Rules\Confirmed;
use Marko\Validation\Rules\Date;
use Marko\Validation\Rules\Different;
use Marko\Validation\Rules\Email;
use Marko\Validation\Rules\In;
use Marko\Validation\Rules\Integer;
use Marko\Validation\Rules\Max;
use Marko\Validation\Rules\Min;
use Marko\Validation\Rules\NotIn;
use Marko\Validation\Rules\Nullable;
use Marko\Validation\Rules\Numeric;
use Marko\Validation\Rules\Regex;
use Marko\Validation\Rules\Required;
use Marko\Validation\Rules\Same;
use Marko\Validation\Rules\StringType;
use Marko\Validation\Rules\Url;

class RuleParser
{
    /**
     * @return array<RuleInterface>
     */
    public function parse(
        mixed $rules,
    ): array {
        if ($rules instanceof RuleInterface) {
            return [$rules];
        }

        if (is_string($rules)) {
            return $this->parseString($rules);
        }

        if (is_array($rules)) {
            return $this->parseArray($rules);
        }

        return [];
    }

    /**
     * @return array<RuleInterface>
     */
    private function parseString(
        string $rules,
    ): array {
        $parsed = [];
        $ruleStrings = explode('|', $rules);

        foreach ($ruleStrings as $rule) {
            $parsed[] = $this->parseRule(trim($rule));
        }

        return $parsed;
    }

    /**
     * @param array $rules
     *
     * @return array<RuleInterface>
     */
    private function parseArray(
        array $rules,
    ): array {
        $parsed = [];

        foreach ($rules as $rule) {
            if ($rule instanceof RuleInterface) {
                $parsed[] = $rule;
            } elseif (is_string($rule)) {
                $parsed = [...$parsed, ...$this->parseString($rule)];
            }
        }

        return $parsed;
    }

    private function parseRule(
        string $rule,
    ): RuleInterface {
        if (str_contains($rule, ':')) {
            [$name, $parameters] = explode(':', $rule, 2);
            $params = str_getcsv($parameters, ',', '"', '');
        } else {
            $name = $rule;
            $params = [];
        }

        return match ($name) {
            'required' => new Required(),
            'email' => new Email(),
            'url' => new Url(),
            'numeric' => new Numeric(),
            'integer' => new Integer(),
            'string' => new StringType(),
            'array' => new ArrayType(),
            'boolean', 'bool' => new Boolean(),
            'alpha' => new Alpha(),
            'alpha_num' => new AlphaNumeric(),
            'confirmed' => new Confirmed(),
            'nullable' => new Nullable(),
            'min' => new Min((float) ($params[0] ?? 0)),
            'max' => new Max((float) ($params[0] ?? 0)),
            'between' => new Between(
                (float) ($params[0] ?? 0),
                (float) ($params[1] ?? 0),
            ),
            'in' => new In(...$params),
            'not_in' => new NotIn(...$params),
            'same' => new Same($params[0] ?? ''),
            'different' => new Different($params[0] ?? ''),
            'regex' => new Regex($params[0] ?? '//'),
            'date' => new Date($params[0] ?? null),
            default => throw new InvalidArgumentException("Unknown validation rule: $name"),
        };
    }
}
