<?php

declare(strict_types=1);

namespace Marko\Testing\Tests\Unit\Fake\NotificationFixtures;

use Marko\Notification\Contracts\NotifiableInterface;

readonly class FixtureNotifiable implements NotifiableInterface
{
    public function __construct(
        private string|int $id,
        private string $type = 'App\\User',
    ) {}

    public function routeNotificationFor(
        string $channel,
    ): mixed {
        return null;
    }

    public function getNotifiableId(): string|int
    {
        return $this->id;
    }

    public function getNotifiableType(): string
    {
        return $this->type;
    }
}
