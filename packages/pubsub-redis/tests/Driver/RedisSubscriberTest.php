<?php

declare(strict_types=1);

use Amp\Pipeline\Queue;
use Amp\Redis\RedisSubscription as AmphpRedisSubscription;
use Marko\PubSub\PubSubConfig;
use Marko\PubSub\Redis\Driver\AmphpRedisSubscriberInterface;
use Marko\PubSub\Redis\Driver\RedisSubscriber;
use Marko\PubSub\Redis\RedisPubSubConnection;
use Marko\PubSub\SubscriberInterface;
use Marko\PubSub\Subscription;
use Marko\Testing\Fake\FakeConfigRepository;

class SpyAmphpRedisSubscriber implements AmphpRedisSubscriberInterface
{
    /** @var array<string, AmphpRedisSubscription> */
    private array $channelSubscriptions;

    /** @var array<string, AmphpRedisSubscription> */
    private array $patternSubscriptions;

    /** @var array<int, string> */
    public array $subscribedChannels = [];

    /** @var array<int, string> */
    public array $subscribedPatterns = [];

    /**
     * @param array<string, AmphpRedisSubscription> $channelSubscriptions
     * @param array<string, AmphpRedisSubscription> $patternSubscriptions
     */
    public function __construct(array $channelSubscriptions = [], array $patternSubscriptions = [])
    {
        $this->channelSubscriptions = $channelSubscriptions;
        $this->patternSubscriptions = $patternSubscriptions;
    }

    public function subscribe(string $channel): AmphpRedisSubscription
    {
        $this->subscribedChannels[] = $channel;

        return $this->channelSubscriptions[$channel] ?? makeSubscriberTestEmptySubscription();
    }

    public function subscribeToPattern(string $pattern): AmphpRedisSubscription
    {
        $this->subscribedPatterns[] = $pattern;

        return $this->patternSubscriptions[$pattern] ?? makeSubscriberTestEmptySubscription();
    }
}

readonly class TestableRedisSubscriber extends RedisSubscriber
{
    public SpyAmphpRedisSubscriber $spy;

    public function __construct(
        RedisPubSubConnection $connection,
        PubSubConfig $config,
        SpyAmphpRedisSubscriber $spy,
    ) {
        parent::__construct($connection, $config);
        $this->spy = $spy;
    }

    protected function createAmphpSubscriber(): AmphpRedisSubscriberInterface
    {
        return $this->spy;
    }
}

function createSubscriberPubSubConfig(string $prefix = 'marko:'): PubSubConfig
{
    return new PubSubConfig(new FakeConfigRepository([
        'pubsub.driver' => 'redis',
        'pubsub.prefix' => $prefix,
    ]));
}

function makeSubscriberTestEmptySubscription(): AmphpRedisSubscription
{
    $queue = new Queue();
    $queue->complete();

    return new AmphpRedisSubscription($queue->iterate(), static function (): void {});
}

/**
 * @param array<string, AmphpRedisSubscription> $channelSubscriptions
 * @param array<string, AmphpRedisSubscription> $patternSubscriptions
 */
function createTestableRedisSubscriber(
    array $channelSubscriptions = [],
    array $patternSubscriptions = [],
    string $prefix = 'marko:',
): TestableRedisSubscriber {
    $config = createSubscriberPubSubConfig($prefix);
    $connection = new RedisPubSubConnection();
    $spy = new SpyAmphpRedisSubscriber($channelSubscriptions, $patternSubscriptions);

    return new TestableRedisSubscriber($connection, $config, $spy);
}

it('creates RedisSubscriber implementing SubscriberInterface', function (): void {
    $subscriber = createTestableRedisSubscriber();

    expect($subscriber)->toBeInstanceOf(SubscriberInterface::class)
        ->and($subscriber)->toBeInstanceOf(RedisSubscriber::class);
});

it('subscribes to channels with prefix applied', function (): void {
    $subscriber = createTestableRedisSubscriber(prefix: 'myapp:');
    $subscription = $subscriber->subscribe('orders');

    expect($subscription)->toBeInstanceOf(Subscription::class)
        ->and($subscriber->spy->subscribedChannels)->toBe(['myapp:orders']);
});

it('subscribes to patterns with prefix applied via psubscribe', function (): void {
    $subscriber = createTestableRedisSubscriber(prefix: 'myapp:');
    $subscription = $subscriber->psubscribe('events:*');

    expect($subscription)->toBeInstanceOf(Subscription::class)
        ->and($subscriber->spy->subscribedPatterns)->toBe(['myapp:events:*']);
});
