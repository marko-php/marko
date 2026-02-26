<?php

declare(strict_types=1);

namespace Marko\PubSub\Redis;

use Amp\Redis\Connection\RedisConnector;
use Amp\Redis\RedisClient;

use function Amp\Redis\createRedisClient;
use function Amp\Redis\createRedisConnector;

class RedisPubSubConnection
{
    private ?RedisClient $client = null;

    private ?RedisConnector $connector = null;

    public function __construct(
        public readonly string $host = '127.0.0.1',
        public readonly int $port = 6379,
        public readonly ?string $password = null,
        public readonly int $database = 0,
        public readonly string $prefix = 'marko:',
    ) {}

    public function client(): RedisClient
    {
        if ($this->client === null) {
            $this->client = $this->createClient();
        }

        return $this->client;
    }

    public function connector(): RedisConnector
    {
        if ($this->connector === null) {
            $this->connector = $this->createConnector();
        }

        return $this->connector;
    }

    public function disconnect(): void
    {
        $this->client = null;
        $this->connector = null;
    }

    public function isConnected(): bool
    {
        return $this->client !== null;
    }

    protected function createClient(): RedisClient
    {
        return createRedisClient("tcp://$this->host:$this->port");
    }

    protected function createConnector(): RedisConnector
    {
        return createRedisConnector("tcp://$this->host:$this->port");
    }
}
