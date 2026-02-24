<?php

declare(strict_types=1);

namespace Marko\Cache\Redis;

use Predis\Client;
use Predis\ClientInterface;

class RedisConnection
{
    private ?ClientInterface $client = null;

    public function __construct(
        public readonly string $host = '127.0.0.1',
        public readonly int $port = 6379,
        public readonly ?string $password = null,
        public readonly int $database = 0,
        public readonly string $prefix = 'marko:cache:',
    ) {}

    public function client(): ClientInterface
    {
        if ($this->client === null) {
            $this->client = $this->createClient();
        }

        return $this->client;
    }

    public function disconnect(): void
    {
        $this->client = null;
    }

    public function isConnected(): bool
    {
        return $this->client !== null;
    }

    protected function createClient(): ClientInterface
    {
        $parameters = [
            'scheme' => 'tcp',
            'host' => $this->host,
            'port' => $this->port,
            'database' => $this->database,
        ];

        if ($this->password !== null) {
            $parameters['password'] = $this->password;
        }

        return new Client($parameters);
    }
}
