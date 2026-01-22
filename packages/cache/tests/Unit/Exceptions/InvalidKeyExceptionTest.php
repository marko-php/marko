<?php

declare(strict_types=1);

use Marko\Cache\Exceptions\CacheException;
use Marko\Cache\Exceptions\InvalidKeyException;

it('extends CacheException', function () {
    $exception = InvalidKeyException::forKey('bad/key');

    expect($exception)->toBeInstanceOf(CacheException::class);
});

it('creates exception for invalid key', function () {
    $exception = InvalidKeyException::forKey('bad/key');

    expect($exception->getMessage())->toBe("Invalid cache key: 'bad/key'")
        ->and($exception->getContext())->toBe('Key contains invalid characters');
});

it('creates exception for empty key', function () {
    $exception = InvalidKeyException::emptyKey();

    expect($exception->getMessage())->toBe('Cache key cannot be empty')
        ->and($exception->getContext())->toBe('An empty string was provided as cache key')
        ->and($exception->getSuggestion())->toBe('Provide a non-empty string as the cache key');
});

it('validates empty key as invalid', function () {
    expect(InvalidKeyException::isValidKey(''))->toBeFalse();
});

it('validates key with forward slash as invalid', function () {
    expect(InvalidKeyException::isValidKey('path/to/key'))->toBeFalse();
});

it('validates key with backslash as invalid', function () {
    expect(InvalidKeyException::isValidKey('path\\to\\key'))->toBeFalse();
});

it('validates key with colon as invalid', function () {
    expect(InvalidKeyException::isValidKey('namespace:key'))->toBeFalse();
});

it('validates key with asterisk as invalid', function () {
    expect(InvalidKeyException::isValidKey('wild*card'))->toBeFalse();
});

it('validates key with question mark as invalid', function () {
    expect(InvalidKeyException::isValidKey('query?param'))->toBeFalse();
});

it('validates key with double quotes as invalid', function () {
    expect(InvalidKeyException::isValidKey('with"quotes'))->toBeFalse();
});

it('validates key with angle brackets as invalid', function () {
    expect(InvalidKeyException::isValidKey('with<angle>'))->toBeFalse();
});

it('validates key with pipe as invalid', function () {
    expect(InvalidKeyException::isValidKey('pipe|char'))->toBeFalse();
});

it('validates key with curly braces as invalid', function () {
    expect(InvalidKeyException::isValidKey('with{braces}'))->toBeFalse();
});

it('validates simple key as valid', function () {
    expect(InvalidKeyException::isValidKey('valid-key'))->toBeTrue();
});

it('validates key with dots as valid', function () {
    expect(InvalidKeyException::isValidKey('user.profile.settings'))->toBeTrue();
});

it('validates key with underscores as valid', function () {
    expect(InvalidKeyException::isValidKey('user_profile_settings'))->toBeTrue();
});

it('validates key with numbers as valid', function () {
    expect(InvalidKeyException::isValidKey('user123'))->toBeTrue();
});

it('validates alphanumeric key as valid', function () {
    expect(InvalidKeyException::isValidKey('User123Data'))->toBeTrue();
});
