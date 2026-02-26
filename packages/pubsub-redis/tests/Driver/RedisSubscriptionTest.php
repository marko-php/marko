<?php

declare(strict_types=1);

use Amp\Pipeline\DisposedException;
use Amp\Pipeline\Queue;
use Amp\Redis\RedisSubscription as AmphpRedisSubscription;
use Marko\PubSub\Message;
use Marko\PubSub\Redis\Driver\RedisSubscription;
use Marko\PubSub\Subscription;

function makeRedisSubscriptionEmptyAmphpSub(): AmphpRedisSubscription
{
    $queue = new Queue();
    $queue->complete();

    return new AmphpRedisSubscription($queue->iterate(), static function (): void {});
}

/**
 * @param array<int, mixed> $messages
 */
function makeRedisSubscriptionWithMessages(array $messages): AmphpRedisSubscription
{
    $queue = new Queue(count($messages));
    foreach ($messages as $message) {
        $queue->push($message);
    }
    $queue->complete();

    return new AmphpRedisSubscription($queue->iterate(), static function (): void {});
}

it('creates RedisSubscription implementing Subscription interface', function (): void {
    $amphpSub = makeRedisSubscriptionEmptyAmphpSub();
    $subscription = new RedisSubscription($amphpSub, 'marko:', 'orders');

    expect($subscription)->toBeInstanceOf(Subscription::class)
        ->and($subscription)->toBeInstanceOf(RedisSubscription::class);
});

it('iterates messages as Message value objects with channel and payload', function (): void {
    $amphpSub = makeRedisSubscriptionWithMessages(['{"id":1}', '{"id":2}']);
    $subscription = new RedisSubscription($amphpSub, 'marko:', 'orders');

    $messages = iterator_to_array($subscription->getIterator());

    expect(count($messages))->toBe(2)
        ->and($messages[0])->toBeInstanceOf(Message::class)
        ->and($messages[0]->channel)->toBe('orders')
        ->and($messages[0]->payload)->toBe('{"id":1}')
        ->and($messages[0]->pattern)->toBeNull()
        ->and($messages[1]->channel)->toBe('orders')
        ->and($messages[1]->payload)->toBe('{"id":2}');
});

it('strips prefix from channel name in received messages', function (): void {
    // Pattern subscriptions: amphp yields [payload, matchedChannel] tuples
    $amphpSub = makeRedisSubscriptionWithMessages([
        ['hello', 'myapp:orders'],
        ['world', 'myapp:events'],
    ]);
    $subscription = new RedisSubscription($amphpSub, 'myapp:', null, 'events:*');

    $messages = iterator_to_array($subscription->getIterator());

    expect(count($messages))->toBe(2)
        ->and($messages[0]->channel)->toBe('orders')
        ->and($messages[0]->payload)->toBe('hello')
        ->and($messages[0]->pattern)->toBe('events:*')
        ->and($messages[1]->channel)->toBe('events')
        ->and($messages[1]->payload)->toBe('world');
});

it('cancels subscription via cancel method', function (): void {
    $queue = new Queue();
    $amphpSub = new AmphpRedisSubscription($queue->iterate(), static function (): void {});
    $subscription = new RedisSubscription($amphpSub, 'marko:', 'orders');

    $subscription->cancel();

    // After cancel(), the iterator is disposed; pushing to queue will throw DisposedException
    expect(fn () => $queue->push('test'))->toThrow(DisposedException::class);
});
