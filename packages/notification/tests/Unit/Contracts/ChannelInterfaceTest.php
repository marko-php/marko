<?php

declare(strict_types=1);

use Marko\Notification\Contracts\ChannelInterface;
use Marko\Notification\Contracts\NotifiableInterface;
use Marko\Notification\Contracts\NotificationInterface;

test('it defines ChannelInterface with send method accepting notifiable and notification', function (): void {
    $reflection = new ReflectionClass(ChannelInterface::class);

    expect($reflection->isInterface())->toBeTrue()
        ->and($reflection->hasMethod('send'))->toBeTrue();

    $method = $reflection->getMethod('send');

    expect($method->isPublic())->toBeTrue();

    $parameters = $method->getParameters();
    expect($parameters)->toHaveCount(2)
        ->and($parameters[0]->getName())->toBe('notifiable')
        ->and($parameters[0]->getType()?->getName())->toBe(NotifiableInterface::class)
        ->and($parameters[1]->getName())->toBe('notification')
        ->and($parameters[1]->getType()?->getName())->toBe(NotificationInterface::class);

    $returnType = $method->getReturnType();
    expect($returnType)->not->toBeNull()
        ->and($returnType->getName())->toBe('void');

    // Verify @throws PHPDoc for ChannelException
    $docComment = $method->getDocComment();
    expect($docComment)->toContain('@throws')
        ->and($docComment)->toContain('ChannelException');
});
