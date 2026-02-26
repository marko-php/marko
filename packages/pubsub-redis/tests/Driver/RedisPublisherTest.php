<?php

declare(strict_types=1);

use Amp\Redis\Connection\RedisLink;
use Amp\Redis\Protocol\RedisResponse;
use Amp\Redis\RedisClient;
use Marko\PubSub\Message;
use Marko\PubSub\PublisherInterface;
use Marko\PubSub\PubSubConfig;
use Marko\PubSub\Redis\Driver\RedisPublisher;
use Marko\PubSub\Redis\RedisPubSubConnection;
use Marko\Testing\Fake\FakeConfigRepository;

readonly class PublisherTestStubRedisResponse implements RedisResponse
{
    public function __construct(
        private int|string|array|null $value = 1,
    ) {}

    public function unwrap(): int|string|array|null
    {
        return $this->value;
    }
}

class PublisherTestStubRedisLink implements RedisLink
{
    /** @var array<int, array{command: string, parameters: array<int|float|string>}> */
    public array $calls = [];

    public function execute(string $command, array $parameters): RedisResponse
    {
        $this->calls[] = ['command' => $command, 'parameters' => $parameters];

        return new PublisherTestStubRedisResponse();
    }
}

function createPubSubConfig(string $prefix = 'marko:'): PubSubConfig
{
    return new PubSubConfig(new FakeConfigRepository([
        'pubsub.driver' => 'redis',
        'pubsub.prefix' => $prefix,
    ]));
}

function createPublisher(
    ?PublisherTestStubRedisLink $stubLink = null,
    string $prefix = 'marko:',
): RedisPublisher {
    $stubLink ??= new PublisherTestStubRedisLink();
    $client = new RedisClient($stubLink);
    $connection = new class ($client) extends RedisPubSubConnection
    {
        public function __construct(
            private readonly RedisClient $mockClient,
        ) {
            parent::__construct();
        }

        protected function createClient(): RedisClient
        {
            return $this->mockClient;
        }
    };
    $config = createPubSubConfig($prefix);

    return new RedisPublisher($connection, $config);
}

it('creates RedisPublisher implementing PublisherInterface', function (): void {
    $publisher = createPublisher();

    expect($publisher)->toBeInstanceOf(PublisherInterface::class)
        ->and($publisher)->toBeInstanceOf(RedisPublisher::class);
});

it('publishes message to prefixed channel via RedisClient', function (): void {
    $stubLink = new PublisherTestStubRedisLink();
    $publisher = createPublisher(stubLink: $stubLink, prefix: 'myapp:');

    $message = new Message(channel: 'orders', payload: '{"id":42}');
    $publisher->publish('orders', $message);

    $publishCalls = array_values(array_filter(
        $stubLink->calls,
        fn ($call) => $call['command'] === 'publish',
    ));

    expect(count($publishCalls))->toBe(1)
        ->and($publishCalls[0]['parameters'][0])->toBe('myapp:orders')
        ->and($publishCalls[0]['parameters'][1])->toBe('{"id":42}');
});
