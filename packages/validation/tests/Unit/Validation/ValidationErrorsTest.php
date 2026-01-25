<?php

declare(strict_types=1);

use Marko\Validation\Validation\ValidationErrors;

it('adds error message for a field', function () {
    $errors = new ValidationErrors();
    $errors->add('email', 'Invalid email');

    expect($errors->get('email'))->toBe(['Invalid email']);
});

it('adds multiple errors for same field', function () {
    $errors = new ValidationErrors();
    $errors->add('password', 'Too short');
    $errors->add('password', 'Missing number');

    expect($errors->get('password'))->toBe(['Too short', 'Missing number']);
});

it('checks if field has errors', function () {
    $errors = new ValidationErrors();
    $errors->add('name', 'Required');

    expect($errors->has('name'))->toBeTrue()
        ->and($errors->has('email'))->toBeFalse();
});

it('returns empty array for missing field', function () {
    $errors = new ValidationErrors();

    expect($errors->get('missing'))->toBe([]);
});

it('returns first error for a field', function () {
    $errors = new ValidationErrors();
    $errors->add('email', 'First error');
    $errors->add('email', 'Second error');

    expect($errors->first('email'))->toBe('First error');
});

it('returns null for first when field has no errors', function () {
    $errors = new ValidationErrors();

    expect($errors->first('missing'))->toBeNull();
});

it('returns all errors', function () {
    $errors = new ValidationErrors();
    $errors->add('name', 'Required');
    $errors->add('email', 'Invalid');

    expect($errors->all())->toBe([
        'name' => ['Required'],
        'email' => ['Invalid'],
    ]);
});

it('returns all field keys', function () {
    $errors = new ValidationErrors();
    $errors->add('name', 'Required');
    $errors->add('email', 'Invalid');

    expect($errors->keys())->toBe(['name', 'email']);
});

it('checks if empty', function () {
    $errors = new ValidationErrors();

    expect($errors->isEmpty())->toBeTrue();

    $errors->add('name', 'Required');

    expect($errors->isEmpty())->toBeFalse();
});

it('checks if not empty', function () {
    $errors = new ValidationErrors();

    expect($errors->isNotEmpty())->toBeFalse();

    $errors->add('name', 'Required');

    expect($errors->isNotEmpty())->toBeTrue();
});

it('counts total errors', function () {
    $errors = new ValidationErrors();
    $errors->add('name', 'Required');
    $errors->add('email', 'Invalid');
    $errors->add('email', 'Taken');

    expect($errors->count())->toBe(3)
        ->and(count($errors))->toBe(3);
});

it('is iterable', function () {
    $errors = new ValidationErrors();
    $errors->add('name', 'Required');
    $errors->add('email', 'Invalid');

    $result = iterator_to_array($errors);

    expect($result)->toBe([
        'name' => ['Required'],
        'email' => ['Invalid'],
    ]);
});

it('converts to flat array', function () {
    $errors = new ValidationErrors();
    $errors->add('name', 'Required');
    $errors->add('email', 'Invalid');

    expect($errors->toFlatArray())->toBe([
        'name: Required',
        'email: Invalid',
    ]);
});

it('initializes with existing errors', function () {
    $errors = new ValidationErrors([
        'name' => ['Required'],
        'email' => ['Invalid'],
    ]);

    expect($errors->all())->toBe([
        'name' => ['Required'],
        'email' => ['Invalid'],
    ]);
});

it('supports fluent interface', function () {
    $errors = new ValidationErrors();

    $result = $errors->add('name', 'Required')
        ->add('email', 'Invalid');

    expect($result)->toBe($errors)
        ->and($errors->count())->toBe(2);
});
