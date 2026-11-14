<?php

declare(strict_types=1);

namespace Marko\Queue\Rabbitmq;

use Exception;
use Marko\Queue\Rabbitmq\Exceptions\RabbitmqException;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPExceptionInterface;

class RabbitmqConnection
{
    private ?AbstractConnection $connection = null;

    private ?AMQPChannel $channel = null;

    /**
     * @param array<string, mixed>|null $tlsOptions
     *
     * @throws RabbitmqException
     */
    public function __construct(
        public readonly string $host = 'localhost',
        public readonly int $port = 5672,
        public readonly string $user = 'guest',
        public readonly string $password = 'guest',
        public readonly string $vhost = '/',
        public readonly ?array $tlsOptions = null,
    ) {
        // guest/guest is public knowledge. RabbitMQ itself only accepts it from loopback by default, so a
        // remote broker that accepts it has had that protection switched off.
        if (
            $user === 'guest'
            && $password === 'guest'
            && !self::isLoopbackHost($host)
        ) {
            throw RabbitmqException::defaultCredentialsOnRemoteHost($host);
        }
    }

    /**
     * Whether the host names this machine: localhost, an address in 127.0.0.0/8, or ::1.
     */
    private static function isLoopbackHost(
        string $host,
    ): bool {
        $host = strtolower(trim($host, '[]'));

        if ($host === 'localhost') {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return str_starts_with($host, '127.');
        }

        return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false
            && inet_pton($host) === inet_pton('::1');
    }

    /**
     * @throws RabbitmqException|Exception
     */
    public function channel(): AMQPChannel
    {
        if ($this->channel === null) {
            try {
                $this->connection = $this->createConnection();
            } catch (AMQPExceptionInterface $e) {
                throw RabbitmqException::connectionFailed($this->host, $this->port, $e);
            }

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
     *
     * @throws Exception
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
