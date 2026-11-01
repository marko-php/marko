<?php

declare(strict_types=1);

use Marko\Validation\Rules\Enum;
use Marko\Validation\Tests\Support\Enums\Priority;
use Marko\Validation\Tests\Support\Enums\Status;
use Marko\Validation\Tests\Support\Enums\Suit;
use Marko\Validation\Validation\Validator;

it('passes a backing value or a case of a string-backed enum', function (): void {
    $rule = Enum::of(Status::class);

    expect($rule->passes('status', 'draft', []))->toBeTrue()
        ->and($rule->passes('status', Status::Published, []))->toBeTrue();
});

it('fails a value that is not one of the cases', function (mixed $value): void {
    $rule = Enum::of(Status::class);

    expect($rule->passes('status', $value, []))->toBeFalse()
        ->and($rule->message('status', $value))
        ->toBe("The status field must be one of: 'draft', 'published', 'archived'.");
})->with(
    ['unknown' => ['deleted'], 'wrong case' => ['Draft'], 'array' => [['draft']], 'other enum' => [Priority::Low]],
);

it('accepts an integer string for an int-backed enum', function (): void {
    $rule = Enum::of(Priority::class);

    expect($rule->passes('priority', 2, []))->toBeTrue()
        ->and($rule->passes('priority', '2', []))->toBeTrue()
        ->and($rule->passes('priority', '2.0', []))->toBeFalse()
        ->and($rule->passes('priority', 3, []))->toBeFalse()
        ->and($rule->message('priority', 3))->toBe('The priority field must be one of: 1, 2.');
});

it('limits the allowed cases with only()', function (): void {
    $rule = Enum::of(Status::class)->only(Status::Draft, Status::Published);

    expect($rule->passes('status', 'draft', []))->toBeTrue()
        ->and($rule->passes('status', 'archived', []))->toBeFalse()
        ->and($rule->passes('status', Status::Archived, []))->toBeFalse()
        ->and($rule->message('status', 'archived'))->toBe("The status field must be one of: 'draft', 'published'.");
});

it('removes cases with except()', function (): void {
    $rule = Enum::of(Status::class)->except(Status::Archived);

    expect($rule->passes('status', 'published', []))->toBeTrue()
        ->and($rule->passes('status', 'archived', []))->toBeFalse();
});

it('is immutable', function (): void {
    $all = Enum::of(Status::class);
    $all->only(Status::Draft);
    $all->except(Status::Draft);

    expect($all->passes('status', 'archived', []))->toBeTrue()
        ->and($all->passes('status', 'draft', []))->toBeTrue();
});

it('throws for a non-backed enum', function (): void {
    Enum::of(Suit::class);
})->throws(InvalidArgumentException::class, 'is a pure enum');

it('throws for a class that is not an enum', function (): void {
    Enum::of(stdClass::class);
})->throws(InvalidArgumentException::class, 'Enum::of() needs a backed enum, but stdClass is not an enum');

it('throws when only() is given a case of another enum', function (): void {
    Enum::of(Status::class)->only(Priority::Low);
})->throws(InvalidArgumentException::class, 'is not a case of');

it('works as an object rule in the validator and has no string form', function (): void {
    $validator = new Validator();

    expect($validator->passes(['status' => 'draft'], ['status' => ['required', Enum::of(Status::class)]]))->toBeTrue()
        ->and(
            $validator->passes(['status' => 'nope'], ['status' => ['required', Enum::of(Status::class)]]),
        )->toBeFalse()
        ->and(fn () => $validator->validate([], ['status' => 'enum:' . Status::class]))
        ->toThrow(InvalidArgumentException::class, 'The enum rule has no string form: use Enum::of(YourEnum::class)');
});
