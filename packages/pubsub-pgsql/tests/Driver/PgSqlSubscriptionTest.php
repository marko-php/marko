<?php

declare(strict_types=1);

use Amp\Postgres\PostgresListener;
use Amp\Postgres\PostgresNotification;
use Marko\PubSub\Message;
use Marko\PubSub\PgSql\Driver\PgSqlSubscription;
use Marko\PubSub\Subscription;

class SubscriptionMockPostgresListener implements PostgresListener, IteratorAggregate
{
    /** @var PostgresNotification[] */
    private array $notifications;

    public bool $unlistenCalled = false;

    private string $channel;

    /** @param PostgresNotification[] $notifications */
    public function __construct(string $channel, array $notifications = [])
    {
        $this->channel = $channel;
        $this->notifications = $notifications;
    }

    public function getChannel(): string
    {
        return $this->channel;
    }

    public function isListening(): bool
    {
        return !$this->unlistenCalled;
    }

    public function unlisten(): void
    {
        $this->unlistenCalled = true;
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->notifications);
    }
}

it('creates PgSqlSubscription implementing Subscription interface', function (): void {
    $subscription = new PgSqlSubscription([], '');

    expect($subscription)->toBeInstanceOf(Subscription::class);
});

it('iterates notifications as Message value objects with channel and payload', function (): void {
    $notifications = [
        new PostgresNotification(channel: 'marko_events', pid: 1, payload: '{"type":"user.created"}'),
        new PostgresNotification(channel: 'marko_events', pid: 2, payload: '{"type":"user.updated"}'),
    ];
    $listener = new SubscriptionMockPostgresListener('marko_events', $notifications);
    $subscription = new PgSqlSubscription([$listener], 'marko_');

    $messages = iterator_to_array($subscription->getIterator());

    expect($messages)->toHaveCount(2)
        ->and($messages[0])->toBeInstanceOf(Message::class)
        ->and($messages[0]->channel)->toBe('events')
        ->and($messages[0]->payload)->toBe('{"type":"user.created"}')
        ->and($messages[1]->channel)->toBe('events')
        ->and($messages[1]->payload)->toBe('{"type":"user.updated"}');
});

it('strips prefix from channel name in received messages', function (): void {
    $notifications = [
        new PostgresNotification(channel: 'app_prefix_orders', pid: 1, payload: 'order-placed'),
    ];
    $listener = new SubscriptionMockPostgresListener('app_prefix_orders', $notifications);
    $subscription = new PgSqlSubscription([$listener], 'app_prefix_');

    $messages = iterator_to_array($subscription->getIterator());

    expect($messages)->toHaveCount(1)
        ->and($messages[0]->channel)->toBe('orders')
        ->and($messages[0]->payload)->toBe('order-placed');
});

it('cancels subscription by calling unlisten on all PostgresListeners', function (): void {
    $listener1 = new SubscriptionMockPostgresListener('marko_events', []);
    $listener2 = new SubscriptionMockPostgresListener('marko_notifications', []);
    $subscription = new PgSqlSubscription([$listener1, $listener2], 'marko_');

    $subscription->cancel();

    expect($listener1->unlistenCalled)->toBeTrue()
        ->and($listener2->unlistenCalled)->toBeTrue();
});
