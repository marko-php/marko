<?php

declare(strict_types=1);

use Marko\Authentication\Event\OtherDevicesLoggedOutEvent;
use Marko\Core\Event\Event;
use Marko\Testing\Fake\FakeAuthenticatable;

it('creates OtherDevicesLoggedOutEvent with user and guard name', function (): void {
    $user = new FakeAuthenticatable(id: 1);

    $event = new OtherDevicesLoggedOutEvent(
        user: $user,
        guard: 'web',
    );

    expect($event)->toBeInstanceOf(Event::class)
        ->and($event->getUser())->toBe($user)
        ->and($event->getGuard())->toBe('web');
});
