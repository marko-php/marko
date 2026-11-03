<?php

declare(strict_types=1);

namespace Marko\Redis;

use Marko\Config\ConfigRepositoryInterface;
use Marko\Redis\Exceptions\RedisConnectionException;

class RedisConnectionFactory implements RedisConnectionFactoryInterface
{
    /** @var array<string, RedisConnectionInterface> */
    private array $connections = [];

    public function __construct(
        private readonly ConfigRepositoryInterface $config,
    ) {}

    /**
     * @throws RedisConnectionException
     */
    public function connection(
        string $name,
    ): RedisConnectionInterface {
        return $this->connections[$name] ??= $this->createConnection($name);
    }

    /**
     * Build the connection from redis.connections.{name}. Every value comes
     * from config; a missing key throws from the config repository.
     *
     * @throws RedisConnectionException
     */
    protected function createConnection(
        string $name,
    ): RedisConnectionInterface {
        $configured = array_keys($this->config->getArray('redis.connections'));

        if (!in_array($name, $configured, true)) {
            throw RedisConnectionException::unknownConnection($name, $configured);
        }

        $key = "redis.connections.$name";
        $password = $this->config->get("$key.password");

        return new RedisConnection(
            host: $this->config->getString("$key.host"),
            port: $this->config->getInt("$key.port"),
            password: $password === null || $password === '' ? null : (string) $password,
            database: $this->config->getInt("$key.database"),
            scheme: $this->config->getString("$key.scheme"),
        );
    }
}
