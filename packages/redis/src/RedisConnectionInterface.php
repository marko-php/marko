<?php

declare(strict_types=1);

namespace Marko\Redis;

use Marko\Redis\Exceptions\RedisConnectionException;
use Predis\ClientInterface;

interface RedisConnectionInterface
{
    /**
     * The connected Predis client, created and connected on first call.
     *
     * @throws RedisConnectionException when the server cannot be reached
     */
    public function client(): ClientInterface;

    /**
     * Release the client; the next client() call connects again.
     */
    public function disconnect(): void;

    /**
     * Whether a client instance is currently held.
     */
    public function isConnected(): bool;
}
