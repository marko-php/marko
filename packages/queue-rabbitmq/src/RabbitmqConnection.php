<?php

declare(strict_types=1);

namespace Marko\Queue\Rabbitmq;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPStreamConnection;

class RabbitmqConnection
{
    private ?AbstractConnection $connection = null;

    private ?AMQPChannel $channel = null;

    /**
     * @param array<string, mixed>|null $tlsOptions
     */
    public function __construct(
        public readonly string $host = 'localhost',
        public readonly int $port = 5672,
        public readonly string $user = 'guest',
        public readonly string $password = 'guest',
        public readonly string $vhost = '/',
        public readonly ?array $tlsOptions = null,
    ) {}

    public function channel(): AMQPChannel
    {
        if ($this->channel === null) {
            $this->connection = $this->createConnection();
            $this->channel = $this->connection->channel();
        }

        return $this->channel;
    }

    public function disconnect(): void
    {
        $this->channel = null;
        $this->connection = null;
    }

    public function isConnected(): bool
    {
        return $this->connection !== null;
    }

    /**
     * Build an SSL stream context from TLS options.
     *
     * @return resource|null
     */
    protected function buildSslContext()
    {
        if ($this->tlsOptions === null) {
            return null;
        }

        $context = stream_context_create();

        foreach ($this->tlsOptions as $key => $value) {
            stream_context_set_option($context, 'ssl', $key, $value);
        }

        return $context;
    }

    /**
     * Create the AMQP connection. Override in tests.
     */
    protected function createConnection(): AbstractConnection
    {
        $context = $this->buildSslContext();

        return new AMQPStreamConnection(
            $this->host,
            $this->port,
            $this->user,
            $this->password,
            $this->vhost,
            context: $context,
        );
    }
}
