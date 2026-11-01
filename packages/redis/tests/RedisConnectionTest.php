<?php

declare(strict_types=1);

namespace Marko\Redis\Tests;

use Marko\Redis\Exceptions\RedisConnectionException;
use Marko\Redis\RedisConnection;
use Marko\Redis\RedisConnectionInterface;
use Predis\Client;
use Predis\ClientInterface;
use Predis\Connection\ConnectionException;
use Predis\Connection\Parameters;
use Predis\Connection\StreamConnection;

function createMockRedisClient(): ClientInterface
{
    /** @noinspection PhpMissingParentConstructorInspection - Test stub intentionally skips parent */
    return new class () extends Client
    {
        /** @noinspection PhpMissingParentConstructorInspection */
        public function __construct() {}
    };
}

function createTestableRedisConnection(
    ?ClientInterface $mockClient = null,
): RedisConnection {
    $mockClient ??= createMockRedisClient();

    return new class ($mockClient) extends RedisConnection
    {
        public function __construct(
            private readonly ClientInterface $mockClient,
        ) {
            parent::__construct(host: '127.0.0.1', port: 6379, password: null, database: 0, scheme: 'tcp');
        }

        protected function createClient(): ClientInterface
        {
            return $this->mockClient;
        }
    };
}

describe('RedisConnection', function (): void {
    it('implements RedisConnectionInterface', function (): void {
        expect(createTestableRedisConnection())->toBeInstanceOf(RedisConnectionInterface::class);
    });

    it(
        'throws RedisConnectionException naming host, port and config file when the connection is refused',
        function (): void {
            $connection = new class () extends RedisConnection
            {
                public function __construct()
                {
                    parent::__construct(
                        host: 'redis.internal',
                        port: 6390,
                        password: null,
                        database: 0,
                        scheme: 'tcp',
                    );
                }

                protected function createClient(): ClientInterface
                {
                    /** @noinspection PhpMissingParentConstructorInspection - Test stub intentionally skips parent */
                    $client = new class () extends Client
                    {
                        /** @noinspection PhpMissingParentConstructorInspection */
                        public function __construct() {}

                        public function connect(): void
                        {
                            throw new ConnectionException(
                                new StreamConnection(new Parameters()),
                                'Connection refused [tcp://redis.internal:6390]',
                            );
                        }
                    };

                    $this->connect($client);

                    return $client;
                }
            };

            try {
                $connection->client();
                $this->fail('Expected RedisConnectionException');
            } catch (RedisConnectionException $e) {
                expect($e->getMessage())->toContain('redis.internal:6390')
                    ->and($e->getSuggestion())->toContain('config/redis.php')
                    ->and($e->getSuggestion())->toContain('REDIS_HOST')
                    ->and($e->getPrevious())->not->toBeNull()
                    ->and($connection->isConnected())->toBeFalse();
            }
        },
    );

    it('exposes the host, port, password, database and scheme it was created with', function (): void {
        $connection = new RedisConnection(
            host: 'redis.example.com',
            port: 6380,
            password: 'secret',
            database: 2,
            scheme: 'tcp',
        );

        expect($connection->host)->toBe('redis.example.com')
            ->and($connection->port)->toBe(6380)
            ->and($connection->password)->toBe('secret')
            ->and($connection->database)->toBe(2)
            ->and($connection->scheme)->toBe('tcp');
    });

    it('builds plain tcp parameters without TLS context options', function (): void {
        $connection = new RedisConnection(host: '127.0.0.1', port: 6379, password: null, database: 1, scheme: 'tcp');

        expect($connection->connectionParameters())->toBe([
            'scheme' => 'tcp',
            'host' => '127.0.0.1',
            'port' => 6379,
            'database' => 1,
        ]);
    });

    it('omits the password from the parameters when it is null', function (): void {
        $connection = new RedisConnection(host: '127.0.0.1', port: 6379, password: null, database: 0, scheme: 'tcp');

        expect($connection->connectionParameters())->not->toHaveKey('password');
    });

    it('builds TLS connection parameters that verify the server certificate and host name', function (): void {
        $connection = new RedisConnection(
            host: 'cache.example.com',
            port: 6380,
            password: 'secret',
            database: 0,
            scheme: 'tls',
        );

        expect($connection->connectionParameters())->toBe([
            'scheme' => 'tls',
            'host' => 'cache.example.com',
            'port' => 6380,
            'database' => 0,
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'peer_name' => 'cache.example.com',
            ],
            'password' => 'secret',
        ]);
    });

    it('throws RedisConnectionException for an unknown scheme', function (): void {
        expect(fn () => new RedisConnection(
            host: '127.0.0.1',
            port: 6379,
            password: null,
            database: 0,
            scheme: 'rediss',
        ))->toThrow(RedisConnectionException::class, "Invalid Redis connection scheme 'rediss'");
    });

    it('lazily connects on first client call', function (): void {
        $connection = createTestableRedisConnection();

        expect($connection->isConnected())->toBeFalse();

        $client = $connection->client();

        expect($connection->isConnected())->toBeTrue()
            ->and($client)->toBeInstanceOf(ClientInterface::class);
    });

    it('returns same client on subsequent calls', function (): void {
        $connection = createTestableRedisConnection();

        expect($connection->client())->toBe($connection->client());
    });

    it('disconnects and clears client reference', function (): void {
        $connection = createTestableRedisConnection();

        $connection->client();
        $connection->disconnect();

        expect($connection->isConnected())->toBeFalse();
    });

    it('reconnects after disconnect', function (): void {
        $connection = createTestableRedisConnection();

        $connection->client();
        $connection->disconnect();
        $connection->client();

        expect($connection->isConnected())->toBeTrue();
    });
});
