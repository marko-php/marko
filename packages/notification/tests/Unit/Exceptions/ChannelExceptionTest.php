<?php

declare(strict_types=1);

use Marko\Notification\Exceptions\ChannelException;
use Marko\Notification\Exceptions\NotificationException;

test(
    'it defines ChannelException extending NotificationException with routeMissing and deliveryFailed factories',
    function (): void {
        $reflection = new ReflectionClass(ChannelException::class);
    
        expect($reflection->isSubclassOf(NotificationException::class))->toBeTrue()
            ->and($reflection->hasMethod('routeMissing'))->toBeTrue()
            ->and($reflection->hasMethod('deliveryFailed'))->toBeTrue();
    }
);

test('it creates ChannelException with context and suggestion via routeMissing factory', function (): void {
    $exception = ChannelException::routeMissing('mail', 'App\\Entity\\User');

    expect($exception)
        ->toBeInstanceOf(ChannelException::class)
        ->toBeInstanceOf(NotificationException::class)
        ->getMessage()->toContain('mail')
        ->getContext()->toContain('App\\Entity\\User')
        ->getContext()->toContain('routeNotificationFor')
        ->getSuggestion()->toContain('routeNotificationFor');
});

test('deliveryFailed factory creates exception with channel and error', function (): void {
    $exception = ChannelException::deliveryFailed('database', 'Connection lost');

    expect($exception)
        ->toBeInstanceOf(ChannelException::class)
        ->getMessage()->toContain('database')
        ->getContext()->toContain('Connection lost')
        ->getSuggestion()->not->toBeEmpty();
});
