<?php

declare(strict_types=1);

namespace Marko\Queue\Rabbitmq\Tests;

use Marko\Queue\Rabbitmq\RabbitmqConnection;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;

function createMockAmqpConnection(): AbstractConnection
{
    /** @noinspection PhpMissingParentConstructorInspection - Test stub intentionally skips parent */
    $mockChannel = new class () extends AMQPChannel
    {
        /** @noinspection PhpMissingParentConstructorInspection */
        public function __construct() {}
    };

    /** @noinspection PhpMissingParentConstructorInspection - Test stub intentionally skips parent */
    return new class ($mockChannel) extends AbstractConnection
    {
        /** @noinspection PhpMissingParentConstructorInspection */
        public function __construct(
            private readonly AMQPChannel $mockChannel,
        ) {}

        public function channel(
            $channel_id = null,
        ): AMQPChannel {
            return $this->mockChannel;
        }

        public function isConnected(): bool
        {
            return true;
        }
    };
}

function createTestableConnection(
    ?AbstractConnection $mockAmqpConnection = null,
): RabbitmqConnection {
    $mockAmqpConnection ??= createMockAmqpConnection();

    return new class ($mockAmqpConnection) extends RabbitmqConnection
    {
        public function __construct(
            private readonly AbstractConnection $mockConnection,
        ) {
            parent::__construct();
        }

        protected function createConnection(): AbstractConnection
        {
            return $this->mockConnection;
        }
    };
}

describe('RabbitmqConnection', function (): void {
    it('creates RabbitmqConnection with default configuration', function (): void {
        $connection = new RabbitmqConnection();

        expect($connection)
            ->toBeInstanceOf(RabbitmqConnection::class)
            ->and($connection->host)->toBe('localhost')
            ->and($connection->port)->toBe(5672)
            ->and($connection->user)->toBe('guest')
            ->and($connection->password)->toBe('guest')
            ->and($connection->vhost)->toBe('/');
    });

    it('creates RabbitmqConnection with custom host port user and vhost', function (): void {
        $connection = new RabbitmqConnection(
            host: 'rabbitmq.example.com',
            port: 5673,
            user: 'admin',
            password: 'secret',
            vhost: '/production',
        );

        expect($connection->host)->toBe('rabbitmq.example.com')
            ->and($connection->port)->toBe(5673)
            ->and($connection->user)->toBe('admin')
            ->and($connection->password)->toBe('secret')
            ->and($connection->vhost)->toBe('/production');
    });

    it('lazily connects on first channel call', function (): void {
        $connection = createTestableConnection();

        // Before calling channel(), should not be connected
        expect($connection->isConnected())->toBeFalse();

        // Call channel() - this should trigger lazy connection
        $channel = $connection->channel();

        expect($connection->isConnected())->toBeTrue()
            ->and($channel)->toBeInstanceOf(AMQPChannel::class);
    });

    it('returns same channel on subsequent calls', function (): void {
        $connection = createTestableConnection();

        $channel1 = $connection->channel();
        $channel2 = $connection->channel();

        expect($channel1)->toBe($channel2);
    });

    it('reports connected status correctly', function (): void {
        $connection = createTestableConnection();

        // Before connecting, should report not connected
        expect($connection->isConnected())->toBeFalse();

        // After channel() call (which triggers connection), should report connected
        $connection->channel();

        expect($connection->isConnected())->toBeTrue();
    });

    it('disconnects and clears channel reference', function (): void {
        $connection = createTestableConnection();

        // Connect by requesting a channel
        $connection->channel();
        expect($connection->isConnected())->toBeTrue();

        // Disconnect
        $connection->disconnect();

        expect($connection->isConnected())->toBeFalse();

        // After disconnect, calling channel() again should reconnect
        $connection->channel();

        expect($connection->isConnected())->toBeTrue();
    });

    it('creates SSL connection when TLS options are provided', function (): void {
        $capturedContext = null;

        $mockAmqpConnection = createMockAmqpConnection();

        $tlsOptions = [
            'cafile' => '/path/to/ca.pem',
            'local_cert' => '/path/to/cert.pem',
            'local_pk' => '/path/to/key.pem',
            'verify_peer' => true,
        ];

        $connection = new class ($tlsOptions, $capturedContext, $mockAmqpConnection) extends RabbitmqConnection
        {
            public function __construct(
                array $tlsOptions,
                /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
                private mixed &$capturedContext,
                private readonly AbstractConnection $mockConnection,
            ) {
                parent::__construct(
                    port: 5671,
                    tlsOptions: $tlsOptions,
                );
            }

            protected function createConnection(): AbstractConnection
            {
                // Call parent to verify it builds the context, but capture the context
                // We can't actually connect, so we intercept and return our mock
                $this->capturedContext = $this->buildSslContext();

                return $this->mockConnection;
            }
        };

        // Trigger connection
        $connection->channel();

        // Verify SSL context was created
        expect($capturedContext)->not->toBeNull()
            ->and(is_resource($capturedContext) || is_object($capturedContext))->toBeTrue();

        // Verify stream context options contain SSL settings
        $options = stream_context_get_options($capturedContext);
        expect($options)->toHaveKey('ssl')
            ->and($options['ssl'])->toHaveKey('cafile')
            ->and($options['ssl']['cafile'])->toBe('/path/to/ca.pem')
            ->and($options['ssl'])->toHaveKey('verify_peer')
            ->and($options['ssl']['verify_peer'])->toBeTrue();
    });
});
