<?php

declare(strict_types=1);

use Marko\Validation\Contracts\RuleInterface;
use Marko\Validation\Rules\Email;

it('implements RuleInterface', function () {
    expect(new Email())->toBeInstanceOf(RuleInterface::class);
});

it('passes for valid email', function () {
    $rule = new Email();

    expect($rule->passes('email', 'test@example.com', []))->toBeTrue();
});

it('passes for email with subdomain', function () {
    $rule = new Email();

    expect($rule->passes('email', 'test@mail.example.com', []))->toBeTrue();
});

it('passes for email with plus sign', function () {
    $rule = new Email();

    expect($rule->passes('email', 'test+tag@example.com', []))->toBeTrue();
});

it('fails for invalid email', function () {
    $rule = new Email();

    expect($rule->passes('email', 'not-an-email', []))->toBeFalse();
});

it('fails for email without domain', function () {
    $rule = new Email();

    expect($rule->passes('email', 'test@', []))->toBeFalse();
});

it('fails for email without at sign', function () {
    $rule = new Email();

    expect($rule->passes('email', 'testexample.com', []))->toBeFalse();
});

it('passes for null value', function () {
    $rule = new Email();

    expect($rule->passes('email', null, []))->toBeTrue();
});

it('passes for empty string', function () {
    $rule = new Email();

    expect($rule->passes('email', '', []))->toBeTrue();
});

it('fails for non-string value', function () {
    $rule = new Email();

    expect($rule->passes('email', 123, []))->toBeFalse();
});

it('returns correct error message', function () {
    $rule = new Email();

    expect($rule->message('email', 'invalid'))->toBe('The email field must be a valid email address.');
});
