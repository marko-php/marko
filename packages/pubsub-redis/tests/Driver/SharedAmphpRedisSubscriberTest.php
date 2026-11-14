<?php

declare(strict_types=1);

use Amp\Pipeline\Queue;
use Amp\Redis\RedisException;
use Amp\Redis\RedisSubscription as AmphpRedisSubscription;
use Marko\PubSub\Redis\Driver\AmphpRedisSubscriberInterface;
use Marko\PubSub\Redis\Driver\SharedAmphpRedisSubscriber;

class RecordingAmphpRedisSubscriber implements AmphpRedisSubscriberInterface
{
    /** @var array<int, string> */
    public array $calls = [];

    public function subscribe(
        string $channel,
    ): AmphpRedisSubscription {
        $this->calls[] = "subscribe:$channel";

        return makeSharedSubscriberTestSubscription();
    }

    public function subscribeToPattern(
        string $pattern,
    ): AmphpRedisSubscription {
        $this->calls[] = "psubscribe:$pattern";

        return makeSharedSubscriberTestSubscription();
    }
}

function makeSharedSubscriberTestSubscription(): AmphpRedisSubscription
{
    $queue = new Queue();
    $queue->complete();

    return new AmphpRedisSubscription($queue->iterate(), static function (): void {});
}

it('creates the inner subscriber on the first channel subscription only', function (): void {
    $inner = new RecordingAmphpRedisSubscriber();
    $factoryCalls = 0;
    $shared = new SharedAmphpRedisSubscriber(function () use ($inner, &$factoryCalls): AmphpRedisSubscriberInterface {
        $factoryCalls++;

        return $inner;
    });

    $shared->subscribe('a');
    $shared->subscribe('b');
    $shared->subscribe('c');

    expect($factoryCalls)->toBe(1)
        ->and($inner->calls)->toBe(['subscribe:a', 'subscribe:b', 'subscribe:c']);
});

it('delegates pattern subscriptions to the inner subscriber', function (): void {
    $inner = new RecordingAmphpRedisSubscriber();
    $factoryCalls = 0;
    $shared = new SharedAmphpRedisSubscriber(function () use ($inner, &$factoryCalls): AmphpRedisSubscriberInterface {
        $factoryCalls++;

        return $inner;
    });

    $shared->subscribeToPattern('user.*');
    $shared->subscribe('orders');

    expect($factoryCalls)->toBe(1)
        ->and($inner->calls)->toBe(['psubscribe:user.*', 'subscribe:orders']);
});

it('does not call the factory until a subscription is made', function (): void {
    $factoryCalls = 0;
    new SharedAmphpRedisSubscriber(function () use (&$factoryCalls): AmphpRedisSubscriberInterface {
        $factoryCalls++;

        return new RecordingAmphpRedisSubscriber();
    });

    expect($factoryCalls)->toBe(0);
});

it('calls the factory again on the next subscription when the factory threw', function (): void {
    $inner = new RecordingAmphpRedisSubscriber();
    $factoryCalls = 0;
    $shared = new SharedAmphpRedisSubscriber(function () use ($inner, &$factoryCalls): AmphpRedisSubscriberInterface {
        $factoryCalls++;

        if ($factoryCalls === 1) {
            throw new RedisException('Redis is unreachable');
        }

        return $inner;
    });

    expect(fn () => $shared->subscribe('orders'))->toThrow(RedisException::class, 'Redis is unreachable');

    $shared->subscribe('orders');

    expect($factoryCalls)->toBe(2)
        ->and($inner->calls)->toBe(['subscribe:orders']);
});
