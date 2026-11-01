<?php

declare(strict_types=1);

namespace Marko\Redis;

use Marko\Redis\Exceptions\RedisConnectionException;

interface RedisConnectionFactoryInterface
{
    /**
     * The connection configured under redis.connections.{name}. Each name
     * resolves to one shared instance.
     *
     * @throws RedisConnectionException when no connection has that name
     */
    public function connection(string $name): RedisConnectionInterface;
}
