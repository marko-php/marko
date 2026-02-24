<?php

declare(strict_types=1);

use Marko\Core\Exceptions\MarkoException;
use Marko\Testing\Exceptions\AssertionFailedException;

it('creates AssertionFailedException extending MarkoException with message, context, and suggestion', function (): void {
    $exception = new AssertionFailedException(
        message: 'Test assertion failed',
        context: 'some context',
        suggestion: 'some suggestion',
    );

    expect($exception)->toBeInstanceOf(MarkoException::class)
        ->and($exception->getMessage())->toBe('Test assertion failed')
        ->and($exception->getContext())->toBe('some context')
        ->and($exception->getSuggestion())->toBe('some suggestion');
});

it('creates AssertionFailedException with static factory methods for common assertion failures', function (): void {
    expect(AssertionFailedException::expectedDispatched('MyEvent'))
        ->toBeInstanceOf(AssertionFailedException::class)
        ->and(AssertionFailedException::unexpectedDispatched('MyEvent'))
        ->toBeInstanceOf(AssertionFailedException::class)
        ->and(AssertionFailedException::expectedCount('emails', 3, 1))
        ->toBeInstanceOf(AssertionFailedException::class)
        ->and(AssertionFailedException::expectedContains('queue', 'MyJob'))
        ->toBeInstanceOf(AssertionFailedException::class)
        ->and(AssertionFailedException::unexpectedContains('queue', 'MyJob'))
        ->toBeInstanceOf(AssertionFailedException::class)
        ->and(AssertionFailedException::expectedEmpty('logs'))
        ->toBeInstanceOf(AssertionFailedException::class)
        ->and(AssertionFailedException::unexpectedEmpty('logs'))
        ->toBeInstanceOf(AssertionFailedException::class);
});

it('expectedDispatched produces correct message', function (): void {
    $exception = AssertionFailedException::expectedDispatched('App\\Events\\UserCreated');

    expect($exception->getMessage())->toBe('Expected [App\\Events\\UserCreated] to be dispatched but it was not.');
});

it('unexpectedDispatched produces correct message', function (): void {
    $exception = AssertionFailedException::unexpectedDispatched('App\\Events\\UserCreated');

    expect($exception->getMessage())->toBe('Expected [App\\Events\\UserCreated] NOT to be dispatched but it was.');
});

it('expectedCount produces correct message', function (): void {
    $exception = AssertionFailedException::expectedCount('emails', 3, 1);

    expect($exception->getMessage())->toBe('Expected 3 emails but got 1.');
});

it('expectedContains produces correct message', function (): void {
    $exception = AssertionFailedException::expectedContains('queue', 'App\\Jobs\\SendEmail');

    expect($exception->getMessage())->toBe(
        'Expected queue collection to contain App\\Jobs\\SendEmail but it was not found.',
    );
});

it('unexpectedContains produces correct message', function (): void {
    $exception = AssertionFailedException::unexpectedContains('queue', 'App\\Jobs\\SendEmail');

    expect($exception->getMessage())->toBe(
        'Expected queue collection NOT to contain App\\Jobs\\SendEmail but it was found.',
    );
});

it('expectedEmpty produces correct message', function (): void {
    $exception = AssertionFailedException::expectedEmpty('logs');

    expect($exception->getMessage())->toBe('Expected no logs but some were found.');
});

it('unexpectedEmpty produces correct message', function (): void {
    $exception = AssertionFailedException::unexpectedEmpty('logs');

    expect($exception->getMessage())->toBe('Expected at least one logs but none were found.');
});

it('creates assertion for expected authenticated', function (): void {
    $exception = AssertionFailedException::expectedAuthenticated();

    expect($exception)->toBeInstanceOf(AssertionFailedException::class)
        ->and($exception->getMessage())->toBe('Expected user to be authenticated but no user is set.');
});

it('creates assertion for unexpected guest', function (): void {
    $exception = AssertionFailedException::unexpectedGuest();

    expect($exception)->toBeInstanceOf(AssertionFailedException::class)
        ->and($exception->getMessage())->toBe('Expected user to be a guest but a user is authenticated.');
});
