<?php

declare(strict_types=1);

beforeEach(function () {
    // Store original env state
    $this->originalEnv = $_ENV;
});

afterEach(function () {
    // Restore original env state
    $_ENV = $this->originalEnv;

    // Clean up any env vars we set via putenv
    foreach (['TEST_VAR', 'BOOL_VAR', 'NULL_VAR', 'EMPTY_VAR'] as $var) {
        putenv($var);
    }
});

it('returns value from $_ENV', function () {
    $_ENV['TEST_VAR'] = 'from_env';

    expect(env('TEST_VAR'))->toBe('from_env');
});

it('falls back to getenv when not in $_ENV', function () {
    putenv('TEST_VAR=from_getenv');

    expect(env('TEST_VAR'))->toBe('from_getenv');
});

it('returns default when variable not set', function () {
    expect(env('NONEXISTENT_VAR', 'default_value'))->toBe('default_value');
});

it('returns null when variable not set and no default', function () {
    expect(env('NONEXISTENT_VAR'))->toBeNull();
});

it('coerces "true" to boolean true', function () {
    $_ENV['BOOL_VAR'] = 'true';
    expect(env('BOOL_VAR'))->toBeTrue();

    $_ENV['BOOL_VAR'] = 'TRUE';
    expect(env('BOOL_VAR'))->toBeTrue();

    $_ENV['BOOL_VAR'] = '(true)';
    expect(env('BOOL_VAR'))->toBeTrue();

    $_ENV['BOOL_VAR'] = '(TRUE)';
    expect(env('BOOL_VAR'))->toBeTrue();
});

it('coerces "false" to boolean false', function () {
    $_ENV['BOOL_VAR'] = 'false';
    expect(env('BOOL_VAR'))->toBeFalse();

    $_ENV['BOOL_VAR'] = 'FALSE';
    expect(env('BOOL_VAR'))->toBeFalse();

    $_ENV['BOOL_VAR'] = '(false)';
    expect(env('BOOL_VAR'))->toBeFalse();

    $_ENV['BOOL_VAR'] = '(FALSE)';
    expect(env('BOOL_VAR'))->toBeFalse();
});

it('coerces "null" to null', function () {
    $_ENV['NULL_VAR'] = 'null';
    expect(env('NULL_VAR'))->toBeNull();

    $_ENV['NULL_VAR'] = 'NULL';
    expect(env('NULL_VAR'))->toBeNull();

    $_ENV['NULL_VAR'] = '(null)';
    expect(env('NULL_VAR'))->toBeNull();
});

it('coerces "empty" to empty string', function () {
    $_ENV['EMPTY_VAR'] = 'empty';
    expect(env('EMPTY_VAR'))->toBe('');

    $_ENV['EMPTY_VAR'] = 'EMPTY';
    expect(env('EMPTY_VAR'))->toBe('');

    $_ENV['EMPTY_VAR'] = '(empty)';
    expect(env('EMPTY_VAR'))->toBe('');
});

it('returns non-special strings as-is', function () {
    $_ENV['TEST_VAR'] = 'hello';
    expect(env('TEST_VAR'))->toBe('hello');

    $_ENV['TEST_VAR'] = '123';
    expect(env('TEST_VAR'))->toBe('123');

    $_ENV['TEST_VAR'] = 'production';
    expect(env('TEST_VAR'))->toBe('production');
});

it('prefers $_ENV over getenv', function () {
    $_ENV['TEST_VAR'] = 'from_env';
    putenv('TEST_VAR=from_getenv');

    expect(env('TEST_VAR'))->toBe('from_env');
});

it('uses default when value is explicitly null in env', function () {
    // Set 'null' string which gets coerced to actual null
    $_ENV['NULL_VAR'] = 'null';

    // Even though it's set, it becomes null after coercion
    expect(env('NULL_VAR', 'default'))->toBeNull();
});

it('does not use default when value is empty string', function () {
    $_ENV['EMPTY_VAR'] = '';

    // Empty string is a valid value, should not use default
    expect(env('EMPTY_VAR', 'default'))->toBe('');
});

it('does not use default when value coerces to empty string', function () {
    $_ENV['EMPTY_VAR'] = 'empty';

    // 'empty' coerces to '', which is still a set value
    expect(env('EMPTY_VAR', 'default'))->toBe('');
});
