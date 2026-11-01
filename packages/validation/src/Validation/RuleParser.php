<?php

declare(strict_types=1);

namespace Marko\Validation\Validation;

use InvalidArgumentException;
use Marko\Validation\Contracts\NumericAwareRuleInterface;
use Marko\Validation\Contracts\RuleInterface;
use Marko\Validation\Rules\After;
use Marko\Validation\Rules\AfterOrEqual;
use Marko\Validation\Rules\Alpha;
use Marko\Validation\Rules\AlphaNumeric;
use Marko\Validation\Rules\ArrayType;
use Marko\Validation\Rules\Before;
use Marko\Validation\Rules\BeforeOrEqual;
use Marko\Validation\Rules\Between;
use Marko\Validation\Rules\Boolean;
use Marko\Validation\Rules\Confirmed;
use Marko\Validation\Rules\Date;
use Marko\Validation\Rules\Different;
use Marko\Validation\Rules\Digits;
use Marko\Validation\Rules\DigitsBetween;
use Marko\Validation\Rules\Distinct;
use Marko\Validation\Rules\Email;
use Marko\Validation\Rules\File;
use Marko\Validation\Rules\Image;
use Marko\Validation\Rules\In;
use Marko\Validation\Rules\Integer;
use Marko\Validation\Rules\Json;
use Marko\Validation\Rules\ListType;
use Marko\Validation\Rules\Max;
use Marko\Validation\Rules\MaxSize;
use Marko\Validation\Rules\Mimes;
use Marko\Validation\Rules\MimeTypes;
use Marko\Validation\Rules\Min;
use Marko\Validation\Rules\MinSize;
use Marko\Validation\Rules\NotIn;
use Marko\Validation\Rules\Nullable;
use Marko\Validation\Rules\Numeric;
use Marko\Validation\Rules\Regex;
use Marko\Validation\Rules\Required;
use Marko\Validation\Rules\Same;
use Marko\Validation\Rules\Size;
use Marko\Validation\Rules\StringType;
use Marko\Validation\Rules\Ulid;
use Marko\Validation\Rules\Url;
use Marko\Validation\Rules\Uuid;

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
            return $this->applyNumericMode($this->parseString($rules));
        }

        if (is_array($rules)) {
            return $this->applyNumericMode($this->parseArray($rules));
        }

        return [];
    }

    /**
     * Size rules compare numeric strings by value only when the field is declared numeric;
     * otherwise `string|min:8` would accept the password `"9"`.
     *
     * @param array<RuleInterface> $rules
     *
     * @return array<RuleInterface>
     */
    private function applyNumericMode(
        array $rules,
    ): array {
        $isNumeric = array_any(
            $rules,
            static fn (RuleInterface $rule): bool => $rule instanceof Numeric || $rule instanceof Integer,
        );

        if (!$isNumeric) {
            return $rules;
        }

        return array_map(
            static fn (RuleInterface $rule): RuleInterface => $rule instanceof NumericAwareRuleInterface
                ? $rule->asNumeric()
                : $rule,
            $rules,
        );
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
     * @param array<mixed> $rules
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

    /**
     * Unlike min/max, a file size limit never defaults: a missing limit is a bug in the rule string.
     *
     * @param array<?string> $params
     *
     * @throws InvalidArgumentException
     */
    private function kilobytes(
        string $name,
        array $params,
    ): float {
        return $this->number($params[0] ?? null, "The $name rule needs a size in kilobytes, e.g. $name:2048");
    }

    /**
     * @throws InvalidArgumentException With $message when the parameter is missing or not numeric
     */
    private function number(
        ?string $parameter,
        string $message,
    ): float {
        if ($parameter === null || !is_numeric(trim($parameter))) {
            throw new InvalidArgumentException($message);
        }

        return (float) $parameter;
    }

    /**
     * @throws InvalidArgumentException With $message when the parameter is missing or not a whole number
     */
    private function wholeNumber(
        ?string $parameter,
        string $message,
    ): int {
        if ($parameter === null || preg_match('/^[0-9]+$/D', trim($parameter)) !== 1) {
            throw new InvalidArgumentException($message);
        }

        return (int) $parameter;
    }

    /**
     * @param array<?string> $params
     *
     * @throws InvalidArgumentException
     */
    private function uuid(
        array $params,
    ): Uuid {
        if (!isset($params[0])) {
            return new Uuid();
        }

        return new Uuid(
            $this->wholeNumber($params[0], 'The uuid rule takes a version number from 1 to 8, e.g. uuid:4'),
        );
    }

    /**
     * @param array<?string> $params
     *
     * @throws InvalidArgumentException
     */
    private function digitsBetween(
        array $params,
    ): DigitsBetween {
        $message = 'The digits_between rule needs a minimum and maximum number of digits, e.g. digits_between:4,6';

        return new DigitsBetween(
            $this->wholeNumber($params[0] ?? null, $message),
            $this->wholeNumber($params[1] ?? null, $message),
        );
    }

    /**
     * @param array<?string> $params
     *
     * @throws InvalidArgumentException When an option is not strict or ignore_case
     */
    private function distinct(
        array $params,
    ): Distinct {
        $options = array_map(static fn (?string $option): string => trim((string) $option), $params);
        $unknown = array_diff($options, ['strict', 'ignore_case']);

        if ($unknown !== []) {
            throw new InvalidArgumentException(
                'Unknown distinct option: ' . implode(', ', $unknown) . ' (use strict, ignore_case or both)',
            );
        }

        return new Distinct(
            strict: in_array('strict', $options, true),
            ignoreCase: in_array('ignore_case', $options, true),
        );
    }

    /**
     * @throws InvalidArgumentException
     */
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
            'url' => new Url(...array_map(strval(...), $params)),
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
            'file' => new File(),
            'image' => new Image(),
            'mimes' => new Mimes(...array_map(strval(...), $params)),
            'mimetypes' => new MimeTypes(...array_map(strval(...), $params)),
            'max_size' => new MaxSize($this->kilobytes($name, $params)),
            'min_size' => new MinSize($this->kilobytes($name, $params)),
            'uuid' => $this->uuid($params),
            'ulid' => new Ulid(),
            'json' => new Json(),
            'list' => new ListType(),
            'digits' => new Digits(
                $this->wholeNumber($params[0] ?? null, 'The digits rule needs a number of digits, e.g. digits:4'),
            ),
            'digits_between' => $this->digitsBetween($params),
            'size' => new Size($this->number($params[0] ?? null, 'The size rule needs a size, e.g. size:10')),
            'after' => new After((string) ($params[0] ?? '')),
            'after_or_equal' => new AfterOrEqual((string) ($params[0] ?? '')),
            'before' => new Before((string) ($params[0] ?? '')),
            'before_or_equal' => new BeforeOrEqual((string) ($params[0] ?? '')),
            'distinct' => $this->distinct($params),
            'enum' => throw new InvalidArgumentException(
                'The enum rule has no string form: use Enum::of(YourEnum::class) in an array of rules',
            ),
            default => throw new InvalidArgumentException("Unknown validation rule: $name"),
        };
    }
}
