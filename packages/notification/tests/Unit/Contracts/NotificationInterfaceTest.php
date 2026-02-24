<?php

declare(strict_types=1);

use Marko\Mail\Message;
use Marko\Notification\Contracts\NotifiableInterface;
use Marko\Notification\Contracts\NotificationInterface;

test('it defines NotificationInterface with channels, toMail, and toDatabase methods', function (): void {
    $reflection = new ReflectionClass(NotificationInterface::class);

    expect($reflection->isInterface())->toBeTrue()
        ->and($reflection->hasMethod('channels'))->toBeTrue()
        ->and($reflection->hasMethod('toMail'))->toBeTrue()
        ->and($reflection->hasMethod('toDatabase'))->toBeTrue();
});

test('channels method accepts NotifiableInterface and returns array', function (): void {
    $reflection = new ReflectionClass(NotificationInterface::class);
    $method = $reflection->getMethod('channels');

    expect($method->isPublic())->toBeTrue();

    $parameters = $method->getParameters();
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('notifiable')
        ->and($parameters[0]->getType()?->getName())->toBe(NotifiableInterface::class);

    $returnType = $method->getReturnType();
    expect($returnType)->not->toBeNull()
        ->and($returnType->getName())->toBe('array');
});

test('toMail method accepts NotifiableInterface and returns Message', function (): void {
    $reflection = new ReflectionClass(NotificationInterface::class);
    $method = $reflection->getMethod('toMail');

    expect($method->isPublic())->toBeTrue();

    $parameters = $method->getParameters();
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('notifiable')
        ->and($parameters[0]->getType()?->getName())->toBe(NotifiableInterface::class);

    $returnType = $method->getReturnType();
    expect($returnType)->not->toBeNull()
        ->and($returnType->getName())->toBe(Message::class);
});

test('toDatabase method accepts NotifiableInterface and returns array', function (): void {
    $reflection = new ReflectionClass(NotificationInterface::class);
    $method = $reflection->getMethod('toDatabase');

    expect($method->isPublic())->toBeTrue();

    $parameters = $method->getParameters();
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('notifiable')
        ->and($parameters[0]->getType()?->getName())->toBe(NotifiableInterface::class);

    $returnType = $method->getReturnType();
    expect($returnType)->not->toBeNull()
        ->and($returnType->getName())->toBe('array');
});
