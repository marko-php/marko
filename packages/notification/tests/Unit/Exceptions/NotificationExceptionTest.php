<?php

declare(strict_types=1);

use Marko\Core\Exceptions\MarkoException;
use Marko\Notification\Exceptions\NotificationException;

test('it defines NotificationException extending MarkoException with factory methods', function (): void {
    $reflection = new ReflectionClass(NotificationException::class);

    expect($reflection->isSubclassOf(MarkoException::class))->toBeTrue()
        ->and($reflection->hasMethod('unknownChannel'))->toBeTrue()
        ->and($reflection->hasMethod('noQueueAvailable'))->toBeTrue()
        ->and($reflection->hasMethod('sendFailed'))->toBeTrue();
});

test('it creates NotificationException with context and suggestion via unknownChannel factory', function (): void {
    $exception = NotificationException::unknownChannel('sms');

    expect($exception)
        ->toBeInstanceOf(NotificationException::class)
        ->toBeInstanceOf(MarkoException::class)
        ->getMessage()->toContain('sms')
        ->getContext()->toContain('sms')
        ->getSuggestion()->not->toBeEmpty();
});

test('noQueueAvailable factory creates exception with context and suggestion', function (): void {
    $exception = NotificationException::noQueueAvailable();

    expect($exception)
        ->toBeInstanceOf(NotificationException::class)
        ->getMessage()->toContain('queue')
        ->getContext()->toContain('QueueInterface')
        ->getSuggestion()->toContain('composer require');
});

test('sendFailed factory creates exception with channel and reason', function (): void {
    $exception = NotificationException::sendFailed('mail', 'Connection refused');

    expect($exception)
        ->toBeInstanceOf(NotificationException::class)
        ->getMessage()->toContain('mail')
        ->getContext()->toContain('Connection refused')
        ->getSuggestion()->not->toBeEmpty();
});
