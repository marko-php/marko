<?php

declare(strict_types=1);

namespace Marko\Redis;

use Marko\Redis\Exceptions\RedisConnectionException;
use Predis\Client;
use Predis\ClientInterface;
use Predis\CommunicationException;

class RedisConnection implements RedisConnectionInterface
{
    /**
     * Transport schemes: tcp is plain text, tls encrypts the connection and verifies the server
     * certificate against the host name.
     */
    public const array SCHEMES = ['tcp', 'tls'];

    private ?ClientInterface $client = null;

    /**
     * @throws RedisConnectionException when the scheme is not one of SCHEMES
     */
    public function __construct(
        public readonly string $host,
        public readonly int $port,
        public readonly ?string $password,
        public readonly int $database,
        public readonly string $scheme,
    ) {
        if (!in_array($scheme, self::SCHEMES, true)) {
            throw RedisConnectionException::invalidScheme($scheme, self::SCHEMES);
        }
    }

    /**
     * @throws RedisConnectionException
     */
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

    /**
     * The Predis connection parameters. With the tls scheme the server
     * certificate is verified against the system trust store and the host name.
     *
     * @return array<string, mixed>
     */
    public function connectionParameters(): array
    {
        $parameters = [
            'scheme' => $this->scheme,
            'host' => $this->host,
            'port' => $this->port,
            'database' => $this->database,
        ];

        if ($this->scheme === 'tls') {
            $parameters['ssl'] = [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'peer_name' => $this->host,
            ];
        }

        if ($this->password !== null) {
            $parameters['password'] = $this->password;
        }

        return $parameters;
    }

    /**
     * Create and connect the Predis client.
     *
     * @throws RedisConnectionException
     */
    protected function createClient(): ClientInterface
    {
        $client = new Client($this->connectionParameters());
        $this->connect($client);

        return $client;
    }

    /**
     * Open the connection eagerly so a refused connection fails here, with
     * the configured host and port, instead of on the first command.
     *
     * @throws RedisConnectionException
     */
    protected function connect(
        ClientInterface $client,
    ): void {
        try {
            $client->connect();
        } catch (CommunicationException $e) {
            throw RedisConnectionException::connectionFailed($this->host, $this->port, $e);
        }
    }
}
