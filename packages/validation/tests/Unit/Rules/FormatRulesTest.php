<?php

declare(strict_types=1);

use Marko\Validation\Rules\Digits;
use Marko\Validation\Rules\DigitsBetween;
use Marko\Validation\Rules\Json;
use Marko\Validation\Rules\ListType;
use Marko\Validation\Rules\Ulid;
use Marko\Validation\Rules\Uuid;

describe('Uuid', function (): void {
    it('passes a UUID of any version in either case', function (string $uuid): void {
        expect(new Uuid()->passes('id', $uuid, []))->toBeTrue();
    })->with([
        'v4' => ['f47ac10b-58cc-4372-a567-0e02b2c3d479'],
        'v7 upper case' => ['018F3A5C-7B2E-7C3D-9E4F-0A1B2C3D4E5F'],
        'nil' => ['00000000-0000-0000-0000-000000000000'],
    ]);

    it('fails a value that is not a UUID', function (mixed $value): void {
        $rule = new Uuid();

        expect($rule->passes('id', $value, []))->toBeFalse()
            ->and($rule->message('id', $value))->toBe('The id field must be a valid UUID.');
    })->with([
        'no dashes' => ['f47ac10b58cc4372a5670e02b2c3d479'],
        'non-hex' => ['g47ac10b-58cc-4372-a567-0e02b2c3d479'],
        'trailing newline' => ["f47ac10b-58cc-4372-a567-0e02b2c3d479\n"],
        'integer' => [42],
        'array' => [['f47ac10b-58cc-4372-a567-0e02b2c3d479']],
    ]);

    it('checks the version and variant when a version is given', function (): void {
        $rule = new Uuid(4);

        expect($rule->passes('id', 'f47ac10b-58cc-4372-a567-0e02b2c3d479', []))->toBeTrue()
            ->and($rule->passes('id', '018f3a5c-7b2e-7c3d-9e4f-0a1b2c3d4e5f', []))->toBeFalse()
            ->and($rule->passes('id', 'f47ac10b-58cc-4372-c567-0e02b2c3d479', []))->toBeFalse()
            ->and($rule->message('id', 'x'))->toBe('The id field must be a valid version 4 UUID.')
            ->and(new Uuid(7)->passes('id', '018f3a5c-7b2e-7c3d-9e4f-0a1b2c3d4e5f', []))->toBeTrue();
    });

    it('throws for a version outside 1 to 8', function (): void {
        new Uuid(9);
    })->throws(InvalidArgumentException::class, 'UUID version must be from 1 to 8, 9 given');

    it('passes an empty value', function (): void {
        expect(new Uuid()->passes('id', null, []))->toBeTrue()
            ->and(new Uuid()->passes('id', '', []))->toBeTrue();
    });
});

describe('Ulid', function (): void {
    it('passes a ULID in either case', function (): void {
        $rule = new Ulid();

        expect($rule->passes('id', '01ARZ3NDEKTSV4RRFFQ69G5FAV', []))->toBeTrue()
            ->and($rule->passes('id', '01arz3ndektsv4rrffq69g5fav', []))->toBeTrue();
    });

    it('fails a value that is not a ULID', function (mixed $value): void {
        $rule = new Ulid();

        expect($rule->passes('id', $value, []))->toBeFalse()
            ->and($rule->message('id', $value))->toBe('The id field must be a valid ULID.');
    })->with([
        'too short' => ['01ARZ3NDEKTSV4RRFFQ69G5FA'],
        'excluded letter U' => ['01ARZ3NDEKTSV4RRFFQ69G5FAU'],
        'timestamp overflow' => ['81ARZ3NDEKTSV4RRFFQ69G5FAV'],
        'integer' => [123],
    ]);
});

describe('Json', function (): void {
    it('passes a valid JSON string', function (string $json): void {
        expect(new Json()->passes('payload', $json, []))->toBeTrue();
    })->with(['object' => ['{"a":1}'], 'array' => ['[1,2]'], 'scalar' => ['"text"'], 'number' => ['42']]);

    it('fails invalid JSON and non-strings', function (mixed $value): void {
        $rule = new Json();

        expect($rule->passes('payload', $value, []))->toBeFalse()
            ->and($rule->message('payload', $value))->toBe('The payload field must be a valid JSON string.');
    })->with(['broken' => ['{"a":'], 'single quotes' => ["{'a':1}"], 'array' => [['a' => 1]], 'integer' => [42]]);
});

describe('ListType', function (): void {
    it('passes an array with sequential keys from 0', function (): void {
        expect(new ListType()->passes('tags', ['php', 'marko'], []))->toBeTrue();
    });

    it('fails an associative array, a gapped array and a non-array', function (mixed $value): void {
        $rule = new ListType();

        expect($rule->passes('tags', $value, []))->toBeFalse()
            ->and($rule->message('tags', $value))->toBe('The tags field must be a list.');
    })->with([
        'associative' => [['a' => 'php']],
        'gapped' => [[0 => 'php', 2 => 'marko']],
        'string' => ['php'],
    ]);
});

describe('Digits', function (): void {
    it('passes a digit string or non-negative integer of exactly n digits', function (mixed $value): void {
        expect(new Digits(4)->passes('pin', $value, []))->toBeTrue();
    })->with(['string' => ['1234'], 'leading zero' => ['0123'], 'integer' => [1234]]);

    it('fails other lengths and non-digit values', function (mixed $value): void {
        $rule = new Digits(4);

        expect($rule->passes('pin', $value, []))->toBeFalse()
            ->and($rule->message('pin', $value))->toBe('The pin field must be 4 digits.');
    })->with([
        'too short' => ['123'],
        'too long' => ['12345'],
        'decimal' => ['12.4'],
        'signed' => ['-123'],
        'negative integer' => [-123],
        'float' => [1234.0],
        'trailing newline' => ["1234\n"],
    ]);

    it('throws for a length below 1', function (): void {
        new Digits(0);
    })->throws(InvalidArgumentException::class, 'The digits rule needs a number of digits of at least 1');
});

describe('DigitsBetween', function (): void {
    it('passes a digit value whose length is within the range', function (): void {
        $rule = new DigitsBetween(4, 6);

        expect($rule->passes('code', '1234', []))->toBeTrue()
            ->and($rule->passes('code', 123456, []))->toBeTrue();
    });

    it('fails a length outside the range or a non-digit value', function (mixed $value): void {
        $rule = new DigitsBetween(4, 6);

        expect($rule->passes('code', $value, []))->toBeFalse()
            ->and($rule->message('code', $value))->toBe('The code field must be between 4 and 6 digits.');
    })->with(['too short' => ['123'], 'too long' => ['1234567'], 'letters' => ['12ab']]);

    it('throws when the minimum exceeds the maximum', function (): void {
        new DigitsBetween(6, 4);
    })->throws(
        InvalidArgumentException::class,
        'The digits_between rule needs a minimum of at least 1 and no greater than its maximum',
    );
});
