<?php

declare(strict_types=1);

namespace Marko\PubSub;

interface SubscriberInterface
{
    /**
     * Subscribe to one or more channels.
     */
    public function subscribe(string ...$channels): Subscription;

    /**
     * Subscribe to one or more channel patterns.
     */
    public function psubscribe(string ...$patterns): Subscription;
}
