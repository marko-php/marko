<?php

declare(strict_types=1);

use Marko\Api\Exceptions\ApiResourceException;

it('throws ApiResourceException with context and suggestion for invalid resource data', function () {
    $exception = new ApiResourceException(
        'Invalid resource data',
        'The resource field "id" must be an integer',
        'Ensure all resource fields match their declared types',
    );

    expect($exception)->toBeInstanceOf(ApiResourceException::class)
        ->and($exception->getMessage())->toBe('Invalid resource data')
        ->and($exception->getContext())->toBe('The resource field "id" must be an integer')
        ->and($exception->getSuggestion())->toBe('Ensure all resource fields match their declared types');
});

it('stores message correctly', function () {
    $exception = new ApiResourceException('Test error');

    expect($exception->getMessage())->toBe('Test error');
});

it('has empty context by default', function () {
    $exception = new ApiResourceException('Test error');

    expect($exception->getContext())->toBe('');
});

it('has empty suggestion by default', function () {
    $exception = new ApiResourceException('Test error');

    expect($exception->getSuggestion())->toBe('');
});

it('stores context correctly', function () {
    $exception = new ApiResourceException('Test error', 'some context');

    expect($exception->getContext())->toBe('some context');
});

it('stores suggestion correctly', function () {
    $exception = new ApiResourceException('Test error', '', 'try this');

    expect($exception->getSuggestion())->toBe('try this');
});

it('is an instance of Exception', function () {
    $exception = new ApiResourceException('Test error');

    expect($exception)->toBeInstanceOf(Exception::class);
});
