<?php

declare(strict_types=1);

use Amp\Cancellation;
use Amp\Redis\Connection\RedisConnection;
use Amp\Redis\Connection\RedisConnector;
use Amp\Redis\Connection\RedisLink;
use Amp\Redis\Protocol\RedisResponse;
use Amp\Redis\RedisClient;
use Marko\PubSub\Redis\RedisPubSubConnection;

readonly class StubRedisResponse implements RedisResponse
{
    public function __construct(
        private int|string|array|null $value = 1,
    ) {}

    public function unwrap(): int|string|array|null
    {
        return $this->value;
    }
}

class StubRedisLink implements RedisLink
{
    /** @var array<int, array{command: string, parameters: array<int|float|string>}> */
    public array $calls = [];

    public function execute(string $command, array $parameters): RedisResponse
    {
        $this->calls[] = ['command' => $command, 'parameters' => $parameters];

        return new StubRedisResponse();
    }
}

class StubRedisConnector implements RedisConnector
{
    public function connect(?Cancellation $cancellation = null): RedisConnection
    {
        throw new RuntimeException('StubRedisConnector::connect() should not be called in tests');
    }
}

function createStubRedisClient(): RedisClient
{
    return new RedisClient(new StubRedisLink());
}

function createTestableConnection(
    ?RedisClient $mockClient = null,
    ?RedisConnector $mockConnector = null,
): RedisPubSubConnection {
    $mockClient ??= createStubRedisClient();
    $mockConnector ??= new StubRedisConnector();

    return new class ($mockClient, $mockConnector) extends RedisPubSubConnection
    {
        public function __construct(
            private readonly RedisClient $mockClient,
            private readonly RedisConnector $mockConnector,
        ) {
            parent::__construct();
        }

        protected function createClient(): RedisClient
        {
            return $this->mockClient;
        }

        protected function createConnector(): RedisConnector
        {
            return $this->mockConnector;
        }
    };
}

it('creates RedisPubSubConnection with host, port, password, database properties', function (): void {
    $connection = new RedisPubSubConnection();

    expect($connection->host)->toBe('127.0.0.1')
        ->and($connection->port)->toBe(6379)
        ->and($connection->password)->toBeNull()
        ->and($connection->database)->toBe(0)
        ->and($connection->prefix)->toBe('marko:');
});

it('creates RedisClient lazily on first use via protected createClient hook', function (): void {
    $connection = createTestableConnection();

    expect($connection->isConnected())->toBeFalse();

    $client = $connection->client();

    expect($connection->isConnected())->toBeTrue()
        ->and($client)->toBeInstanceOf(RedisClient::class);
});

it('creates RedisConnector lazily on first use via protected createConnector hook', function (): void {
    $mockConnector = new StubRedisConnector();
    $connection = createTestableConnection(mockConnector: $mockConnector);

    $connector = $connection->connector();

    expect($connector)->toBeInstanceOf(RedisConnector::class)
        ->and($connector)->toBe($mockConnector);
});

it('provides disconnect and isConnected methods', function (): void {
    $connection = createTestableConnection();

    expect($connection->isConnected())->toBeFalse();

    $connection->client();

    expect($connection->isConnected())->toBeTrue();

    $connection->disconnect();

    expect($connection->isConnected())->toBeFalse();
});
