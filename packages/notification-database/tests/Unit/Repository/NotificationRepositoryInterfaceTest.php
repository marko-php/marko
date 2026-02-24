<?php

declare(strict_types=1);

use Marko\Notification\Contracts\NotifiableInterface;
use Marko\Notification\Database\Repository\NotificationRepositoryInterface;

test('it defines NotificationRepositoryInterface with forNotifiable and unread methods', function (): void {
    $reflection = new ReflectionClass(NotificationRepositoryInterface::class);

    expect($reflection->isInterface())->toBeTrue()
        ->and($reflection->hasMethod('forNotifiable'))->toBeTrue()
        ->and($reflection->hasMethod('unread'))->toBeTrue();

    // forNotifiable
    $forNotifiable = $reflection->getMethod('forNotifiable');
    expect($forNotifiable->isPublic())->toBeTrue();
    $params = $forNotifiable->getParameters();
    expect($params)->toHaveCount(1)
        ->and($params[0]->getType()?->getName())->toBe(NotifiableInterface::class);
    expect($forNotifiable->getReturnType()?->getName())->toBe('array');

    // unread
    $unread = $reflection->getMethod('unread');
    expect($unread->isPublic())->toBeTrue();
    $params = $unread->getParameters();
    expect($params)->toHaveCount(1)
        ->and($params[0]->getType()?->getName())->toBe(NotifiableInterface::class);
    expect($unread->getReturnType()?->getName())->toBe('array');
});

test('it defines NotificationRepositoryInterface with markAsRead and markAllAsRead methods', function (): void {
    $reflection = new ReflectionClass(NotificationRepositoryInterface::class);

    // markAsRead
    $markAsRead = $reflection->getMethod('markAsRead');
    expect($markAsRead->isPublic())->toBeTrue();
    $params = $markAsRead->getParameters();
    expect($params)->toHaveCount(1)
        ->and($params[0]->getType()?->getName())->toBe('string');
    expect($markAsRead->getReturnType()?->getName())->toBe('void');

    // markAllAsRead
    $markAllAsRead = $reflection->getMethod('markAllAsRead');
    expect($markAllAsRead->isPublic())->toBeTrue();
    $params = $markAllAsRead->getParameters();
    expect($params)->toHaveCount(1)
        ->and($params[0]->getType()?->getName())->toBe(NotifiableInterface::class);
    expect($markAllAsRead->getReturnType()?->getName())->toBe('void');
});

test('it defines NotificationRepositoryInterface with delete, deleteAll, and unreadCount methods', function (): void {
    $reflection = new ReflectionClass(NotificationRepositoryInterface::class);

    // delete
    $delete = $reflection->getMethod('delete');
    expect($delete->isPublic())->toBeTrue();
    $params = $delete->getParameters();
    expect($params)->toHaveCount(1)
        ->and($params[0]->getType()?->getName())->toBe('string');
    expect($delete->getReturnType()?->getName())->toBe('void');

    // deleteAll
    $deleteAll = $reflection->getMethod('deleteAll');
    expect($deleteAll->isPublic())->toBeTrue();
    $params = $deleteAll->getParameters();
    expect($params)->toHaveCount(1)
        ->and($params[0]->getType()?->getName())->toBe(NotifiableInterface::class);
    expect($deleteAll->getReturnType()?->getName())->toBe('void');

    // unreadCount
    $unreadCount = $reflection->getMethod('unreadCount');
    expect($unreadCount->isPublic())->toBeTrue();
    $params = $unreadCount->getParameters();
    expect($params)->toHaveCount(1)
        ->and($params[0]->getType()?->getName())->toBe(NotifiableInterface::class);
    expect($unreadCount->getReturnType()?->getName())->toBe('int');
});
