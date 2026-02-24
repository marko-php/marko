<?php

declare(strict_types=1);

use Marko\Notification\Contracts\NotifiableInterface;

test(
    'it defines NotifiableInterface with routeNotificationFor, getNotifiableId, and getNotifiableType methods',
    function (): void {
        $reflection = new ReflectionClass(NotifiableInterface::class);
    
        expect($reflection->isInterface())->toBeTrue()
            ->and($reflection->hasMethod('routeNotificationFor'))->toBeTrue()
            ->and($reflection->hasMethod('getNotifiableId'))->toBeTrue()
            ->and($reflection->hasMethod('getNotifiableType'))->toBeTrue();
    }
);

test('routeNotificationFor accepts string channel and returns mixed', function (): void {
    $reflection = new ReflectionClass(NotifiableInterface::class);
    $method = $reflection->getMethod('routeNotificationFor');

    expect($method->isPublic())->toBeTrue();

    $parameters = $method->getParameters();
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('channel')
        ->and($parameters[0]->getType()?->getName())->toBe('string');

    $returnType = $method->getReturnType();
    expect($returnType)->not->toBeNull()
        ->and($returnType->getName())->toBe('mixed');
});

test('getNotifiableId returns string or int', function (): void {
    $reflection = new ReflectionClass(NotifiableInterface::class);
    $method = $reflection->getMethod('getNotifiableId');

    expect($method->isPublic())->toBeTrue()
        ->and($method->getParameters())->toHaveCount(0);

    $returnType = $method->getReturnType();
    expect($returnType)->not->toBeNull()
        ->and($returnType)->toBeInstanceOf(ReflectionUnionType::class);
});

test('getNotifiableType returns string', function (): void {
    $reflection = new ReflectionClass(NotifiableInterface::class);
    $method = $reflection->getMethod('getNotifiableType');

    expect($method->isPublic())->toBeTrue()
        ->and($method->getParameters())->toHaveCount(0);

    $returnType = $method->getReturnType();
    expect($returnType)->not->toBeNull()
        ->and($returnType->getName())->toBe('string');
});
