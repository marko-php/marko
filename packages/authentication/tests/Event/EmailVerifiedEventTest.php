<?php

declare(strict_types=1);

use Marko\Authentication\Event\EmailVerifiedEvent;
use Marko\Authentication\Tests\Fixtures\VerifiableUser;

it('creates EmailVerifiedEvent with the verified user', function () {
    $user = new VerifiableUser(id: 1, email: 'ada@example.com');

    $event = new EmailVerifiedEvent(
        user: $user,
    );

    expect($event->user)->toBe($user)
        ->and($event->getUser())->toBe($user)
        ->and(new ReflectionProperty(EmailVerifiedEvent::class, 'user')->isReadOnly())->toBeTrue();
});
