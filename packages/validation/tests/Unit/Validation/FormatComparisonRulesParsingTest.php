<?php

declare(strict_types=1);

use Marko\Validation\Rules\After;
use Marko\Validation\Rules\AfterOrEqual;
use Marko\Validation\Rules\Before;
use Marko\Validation\Rules\BeforeOrEqual;
use Marko\Validation\Rules\Digits;
use Marko\Validation\Rules\DigitsBetween;
use Marko\Validation\Rules\Distinct;
use Marko\Validation\Rules\Json;
use Marko\Validation\Rules\ListType;
use Marko\Validation\Rules\Size;
use Marko\Validation\Rules\Ulid;
use Marko\Validation\Rules\Uuid;
use Marko\Validation\Validation\RuleParser;
use Marko\Validation\Validation\Validator;

it('parses each new rule key', function (string $rule, string $class): void {
    expect(new RuleParser()->parse($rule))->sequence(fn ($parsed) => $parsed->toBeInstanceOf($class));
})->with([
    ['uuid', Uuid::class],
    ['uuid:7', Uuid::class],
    ['ulid', Ulid::class],
    ['json', Json::class],
    ['list', ListType::class],
    ['digits:4', Digits::class],
    ['digits_between:4,6', DigitsBetween::class],
    ['size:3', Size::class],
    ['after:today', After::class],
    ['after_or_equal:starts_at', AfterOrEqual::class],
    ['before:2026-01-01', Before::class],
    ['before_or_equal:ends_at', BeforeOrEqual::class],
    ['distinct', Distinct::class],
    ['distinct:ignore_case', Distinct::class],
]);

it('passes the uuid version through', function (): void {
    $validator = new Validator();

    expect($validator->passes(['id' => '018f3a5c-7b2e-7c3d-9e4f-0a1b2c3d4e5f'], ['id' => 'uuid:7']))->toBeTrue()
        ->and($validator->validate(['id' => '018f3a5c-7b2e-7c3d-9e4f-0a1b2c3d4e5f'], ['id' => 'uuid:4'])->get('id'))
        ->toBe(['The id field must be a valid version 4 UUID.']);
});

it('throws for a missing or invalid parameter', function (string $rule, string $message): void {
    expect(fn () => new RuleParser()->parse($rule))->toThrow(InvalidArgumentException::class, $message);
})->with([
    ['size', 'The size rule needs a size, e.g. size:10'],
    ['size:big', 'The size rule needs a size, e.g. size:10'],
    ['digits', 'The digits rule needs a number of digits, e.g. digits:4'],
    ['digits:4.5', 'The digits rule needs a number of digits, e.g. digits:4'],
    ['digits_between:4', 'The digits_between rule needs a minimum and maximum number of digits, e.g. digits_between:4,6'],
    ['digits_between:a,6', 'The digits_between rule needs a minimum and maximum number of digits, e.g. digits_between:4,6'],
    ['uuid:v4', 'The uuid rule takes a version number from 1 to 8, e.g. uuid:4'],
    ['uuid:9', 'UUID version must be from 1 to 8, 9 given'],
    ['after', 'The after rule needs a date or a field, e.g. after:today or after:starts_at'],
    ['before:', 'The before rule needs a date or a field, e.g. before:today or before:starts_at'],
]);

it('validates a mixed set of new rules end to end', function (): void {
    $errors = new Validator()->validate(
        [
            'id' => 'not-a-uuid',
            'ulid' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'payload' => '{"ok":true}',
            'tags' => ['b' => 'x'],
            'pin' => '12a4',
        ],
        [
            'id' => 'required|uuid',
            'ulid' => 'required|ulid',
            'payload' => 'required|json',
            'tags' => 'required|list',
            'pin' => 'required|digits:4',
        ],
    );

    expect($errors->all())->toBe([
        'id' => ['The id field must be a valid UUID.'],
        'tags' => ['The tags field must be a list.'],
        'pin' => ['The pin field must be 4 digits.'],
    ]);
});
