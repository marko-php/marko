<?php

declare(strict_types=1);

use Marko\Authentication\Event\FailedLoginEvent;
use Marko\Authentication\Event\LoginEvent;
use Marko\Authentication\Event\LogoutEvent;
use Marko\Authentication\Event\PasswordResetEvent;

it('all events are immutable', function () {

    // Check LoginEvent properties are readonly
    $loginEvent = new ReflectionClass(LoginEvent::class);
    expect($loginEvent->getProperty('user')->isReadOnly())->toBeTrue()
        ->and($loginEvent->getProperty('guard')->isReadOnly())->toBeTrue()
        ->and($loginEvent->getProperty('remember')->isReadOnly())->toBeTrue();

    // Check LogoutEvent properties are readonly
    $logoutEvent = new ReflectionClass(LogoutEvent::class);
    expect($logoutEvent->getProperty('user')->isReadOnly())->toBeTrue()
        ->and($logoutEvent->getProperty('guard')->isReadOnly())->toBeTrue();

    // Check FailedLoginEvent properties are readonly
    $failedLoginEvent = new ReflectionClass(FailedLoginEvent::class);
    expect($failedLoginEvent->getProperty('credentials')->isReadOnly())->toBeTrue()
        ->and($failedLoginEvent->getProperty('guard')->isReadOnly())->toBeTrue();

    // Check PasswordResetEvent properties are readonly
    $passwordResetEvent = new ReflectionClass(PasswordResetEvent::class);
    expect($passwordResetEvent->getProperty('user')->isReadOnly())->toBeTrue();
});
