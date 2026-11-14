<?php

declare(strict_types=1);

use function Amp\async;

use Amp\Pipeline\DisposedException;
use Amp\Pipeline\Queue;
use Amp\Redis\RedisSubscription as AmphpRedisSubscription;
use Amp\TimeoutCancellation;
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
function makeRedisSubscriptionWithMessages(
    array $messages,
): AmphpRedisSubscription {
    $queue = new Queue(count($messages));
    foreach ($messages as $message) {
        $queue->push($message);
    }
    $queue->complete();

    return new AmphpRedisSubscription($queue->iterate(), static function (): void {});
}

it('creates RedisSubscription implementing Subscription interface', function (): void {
    $amphpSub = makeRedisSubscriptionEmptyAmphpSub();
    $subscription = new RedisSubscription([$amphpSub], 'marko:', ['orders']);

    expect($subscription)->toBeInstanceOf(Subscription::class)
        ->and($subscription)->toBeInstanceOf(RedisSubscription::class);
});

it('iterates messages as Message value objects with channel and payload', function (): void {
    $amphpSub = makeRedisSubscriptionWithMessages(['{"id":1}', '{"id":2}']);
    $subscription = new RedisSubscription([$amphpSub], 'marko:', ['orders']);

    $messages = iterator_to_array($subscription->getIterator());

    expect($messages)->toHaveCount(2)
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
    $subscription = new RedisSubscription([$amphpSub], 'myapp:', [], ['events:*']);

    $messages = iterator_to_array($subscription->getIterator());

    expect($messages)->toHaveCount(2)
        ->and($messages[0]->channel)->toBe('orders')
        ->and($messages[0]->payload)->toBe('hello')
        ->and($messages[0]->pattern)->toBe('events:*')
        ->and($messages[1]->channel)->toBe('events')
        ->and($messages[1]->payload)->toBe('world');
});

it('delivers a redis message published to a non-first subscribed channel', function (): void {
    $firstSub = makeRedisSubscriptionEmptyAmphpSub();
    $secondSub = makeRedisSubscriptionWithMessages(['payload-from-second']);
    $subscription = new RedisSubscription([$firstSub, $secondSub], 'app:', ['first', 'second']);

    $messages = iterator_to_array($subscription->getIterator());

    expect($messages)->toHaveCount(1)
        ->and($messages[0]->channel)->toBe('second')
        ->and($messages[0]->payload)->toBe('payload-from-second');
});

it('delivers a message on a later channel while an earlier channel is still open', function (): void {
    // The first channel never completes, as a live Redis subscription never does
    $openQueue = new Queue();
    $firstSub = new AmphpRedisSubscription($openQueue->iterate(), static function (): void {});
    $secondSub = makeRedisSubscriptionWithMessages(['payload-from-second']);
    $subscription = new RedisSubscription([$firstSub, $secondSub], 'app:', ['first', 'second']);
    $iterator = $subscription->getIterator();

    $message = async(static fn (): Message => $iterator->current())
        ->await(new TimeoutCancellation(1.0));

    expect($message->channel)->toBe('second')
        ->and($message->payload)->toBe('payload-from-second');

    $subscription->cancel();
});

it('keeps per-channel order when reading several channels at once', function (): void {
    $firstSub = makeRedisSubscriptionWithMessages(['a1', 'a2']);
    $secondSub = makeRedisSubscriptionWithMessages(['b1', 'b2']);
    $subscription = new RedisSubscription([$firstSub, $secondSub], 'app:', ['a', 'b']);

    $payloads = [];

    foreach ($subscription as $message) {
        $payloads[$message->channel][] = $message->payload;
    }

    expect($payloads)->toBe(['a' => ['a1', 'a2'], 'b' => ['b1', 'b2']]);
});

it('cancels subscription via cancel method', function (): void {
    $queue = new Queue();
    $amphpSub = new AmphpRedisSubscription($queue->iterate(), static function (): void {});
    $subscription = new RedisSubscription([$amphpSub], 'marko:', ['orders']);

    $subscription->cancel();

    // After cancel(), the iterator is disposed; pushing to queue will throw DisposedException
    expect(fn () => $queue->push('test'))->toThrow(DisposedException::class);
});
