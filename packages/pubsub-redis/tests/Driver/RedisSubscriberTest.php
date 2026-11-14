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
    /** @var array<int, string> */
    public array $subscribedChannels = [];

    /** @var array<int, string> */
    public array $subscribedPatterns = [];

    public int $createCount = 0;

    /**
     * @param array<string, AmphpRedisSubscription> $channelSubscriptions
     * @param array<string, AmphpRedisSubscription> $patternSubscriptions
     */
    public function __construct(
        private array $channelSubscriptions = [],
        private array $patternSubscriptions = [],
    ) {}

    public function subscribe(
        string $channel,
    ): AmphpRedisSubscription {
        $this->subscribedChannels[] = $channel;

        return $this->channelSubscriptions[$channel] ?? makeSubscriberTestEmptySubscription();
    }

    public function subscribeToPattern(
        string $pattern,
    ): AmphpRedisSubscription {
        $this->subscribedPatterns[] = $pattern;

        return $this->patternSubscriptions[$pattern] ?? makeSubscriberTestEmptySubscription();
    }
}

readonly class TestableRedisSubscriber extends RedisSubscriber
{
    public function __construct(
        RedisPubSubConnection $connection,
        PubSubConfig $config,
        public SpyAmphpRedisSubscriber $spy,
    ) {
        parent::__construct($connection, $config);
    }

    protected function createAmphpSubscriber(): AmphpRedisSubscriberInterface
    {
        $this->spy->createCount++;

        return $this->spy;
    }
}

function createSubscriberPubSubConfig(
    string $prefix = 'marko:',
): PubSubConfig {
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

it('subscribes a redis subscription to every requested channel', function (): void {
    $subscriber = createTestableRedisSubscriber(prefix: 'app:');
    $subscription = $subscriber->subscribe('orders', 'notifications', 'alerts');

    expect($subscription)->toBeInstanceOf(Subscription::class)
        ->and($subscriber->spy->subscribedChannels)->toBe(['app:orders', 'app:notifications', 'app:alerts']);
});

it('subscribes a redis pattern subscription to every requested pattern', function (): void {
    $subscriber = createTestableRedisSubscriber(prefix: 'app:');
    $subscription = $subscriber->psubscribe('orders:*', 'events:*');

    expect($subscription)->toBeInstanceOf(Subscription::class)
        ->and($subscriber->spy->subscribedPatterns)->toBe(['app:orders:*', 'app:events:*']);
});

it('creates the amphp subscriber once across many subscribe calls', function (): void {
    $subscriber = createTestableRedisSubscriber(prefix: 'app:');

    for ($i = 1; $i <= 25; $i++) {
        $subscriber->subscribe("user.$i");
    }

    expect($subscriber->spy->createCount)->toBe(1)
        ->and($subscriber->spy->subscribedChannels)->toHaveCount(25);
});

it('creates the amphp subscriber once across subscribe and psubscribe calls', function (): void {
    $subscriber = createTestableRedisSubscriber(prefix: 'app:');

    $subscriber->subscribe('orders');
    $subscriber->psubscribe('events:*');
    $subscriber->subscribe('alerts', 'notifications');
    $subscriber->psubscribe('users:*');

    expect($subscriber->spy->createCount)->toBe(1)
        ->and($subscriber->spy->subscribedChannels)->toBe(['app:orders', 'app:alerts', 'app:notifications'])
        ->and($subscriber->spy->subscribedPatterns)->toBe(['app:events:*', 'app:users:*']);
});

it('does not create the amphp subscriber until the first subscription', function (): void {
    $subscriber = createTestableRedisSubscriber();

    expect($subscriber->spy->createCount)->toBe(0);

    $subscriber->subscribe('orders');

    expect($subscriber->spy->createCount)->toBe(1);
});
