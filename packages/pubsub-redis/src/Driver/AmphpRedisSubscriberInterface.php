<?php

declare(strict_types=1);

namespace Marko\PubSub\Redis\Driver;

use Amp\Redis\RedisSubscription as AmphpRedisSubscription;

interface AmphpRedisSubscriberInterface
{
    public function subscribe(string $channel): AmphpRedisSubscription;

    public function subscribeToPattern(string $pattern): AmphpRedisSubscription;
}
