<?php

declare(strict_types=1);

namespace Marko\Broadcasting\Amphp\Server;

use function Amp\async;

use Marko\Broadcasting\Amphp\AmphpBroadcastingConfig;
use Marko\Log\Contracts\LoggerInterface;
use Marko\PubSub\Message;
use Marko\PubSub\SubscriberInterface;
use Marko\PubSub\Subscription;
use stdClass;
use Throwable;

/**
 * Fans pub/sub messages out to open streams. Each channel has ONE shared subscription no matter
 * how many streams listen to it; it is opened by the first stream and cancelled when the last
 * one leaves.
 */
class ChannelHub
{
    /**
     * @var array<string, ChannelState>
     */
    private array $channels = [];

    /**
     * @var array<int, SseConnection>
     */
    private array $connections = [];

    public function __construct(
        private readonly SubscriberInterface $subscriber,
        private readonly ReplayBuffer $replayBuffer,
        private readonly AmphpBroadcastingConfig $amphpBroadcastingConfig,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Subscribe the connection's channels (sharing existing subscriptions), write the
     * Last-Event-ID replay or a reset frame, then start live delivery.
     *
     * Everything after the subscriptions are ready runs without suspending, so no live event can
     * slip between the replay and live delivery or be delivered twice.
     *
     * @throws Throwable When a pub/sub subscription cannot be opened
     */
    public function join(
        SseConnection $connection,
        ?string $lastEventId = null,
    ): void {
        $states = [];

        foreach (array_unique($connection->channels) as $channel) {
            $state = $this->channels[$channel] ?? $this->open($channel);
            $state->joining++;
            $states[] = $state;
        }

        try {
            foreach ($states as $state) {
                $state->ready->getFuture()->await();
            }
        } catch (Throwable $e) {
            $this->abandon($states);

            throw $e;
        }

        if ($connection->isClosed()) {
            $this->abandon($states);

            return;
        }

        foreach ($states as $state) {
            $state->joining--;
        }

        if (
            $lastEventId !== null
            && $lastEventId !== ''
        ) {
            $events = $this->replayBuffer->replay($connection->channels, $lastEventId);

            if ($events === null) {
                $connection->send(SseFrame::reset('last-event-id-unavailable'));
            } else {
                foreach ($events as $event) {
                    $connection->send($event->toFrame());
                }
            }
        }

        foreach ($states as $state) {
            $state->connections[$connection->id] = $connection;
        }

        $this->connections[$connection->id] = $connection;
        $connection->onClose($this->leave(...));
    }

    /**
     * Remove a closed connection; the last one to leave a channel cancels its subscription.
     */
    public function leave(
        SseConnection $connection,
    ): void {
        unset($this->connections[$connection->id]);

        foreach (array_unique($connection->channels) as $channel) {
            $state = $this->channels[$channel] ?? null;

            if ($state === null) {
                continue;
            }

            unset($state->connections[$connection->id]);
            $this->closeIfUnused($state);
        }

        $connection->close();
    }

    /**
     * Write a heartbeat comment to every open stream.
     */
    public function heartbeat(): void
    {
        foreach ($this->connections as $connection) {
            $connection->send(SseFrame::HEARTBEAT);
        }
    }

    /**
     * Close every stream (after an optional final frame) and cancel every subscription.
     */
    public function closeAll(
        ?string $finalFrame = null,
    ): void {
        foreach ($this->connections as $connection) {
            $connection->close($finalFrame);
        }

        foreach ($this->channels as $state) {
            $this->cancel($state);
        }

        $this->connections = [];
        $this->channels = [];
    }

    public function connectionCount(): int
    {
        return count($this->connections);
    }

    /**
     * @return array<string, int> Open streams per channel
     */
    public function channelCounts(): array
    {
        return array_map(fn (ChannelState $state): int => count($state->connections), $this->channels);
    }

    public function hasChannel(
        string $channel,
    ): bool {
        return isset($this->channels[$channel]);
    }

    private function open(
        string $channel,
    ): ChannelState {
        $state = new ChannelState($channel);
        $this->channels[$channel] = $state;

        async(function () use ($state): void {
            try {
                $subscription = $this->subscriber->subscribe(
                    $this->amphpBroadcastingConfig->channelPrefix . $state->name,
                );
            } catch (Throwable $e) {
                if (($this->channels[$state->name] ?? null) === $state) {
                    unset($this->channels[$state->name]);
                }

                $this->logger->error("Failed to subscribe to broadcast channel '$state->name'", [
                    'exception' => $e,
                ]);
                $state->ready->error($e);

                return;
            }

            $state->subscription = $subscription;

            if ($state->cancelled) {
                $subscription->cancel();
                $state->ready->complete();

                return;
            }

            $this->replayBuffer->openChannel($state->name);
            $state->ready->complete();
            $this->pump($state, $subscription);
        })->ignore();

        return $state;
    }

    private function pump(
        ChannelState $state,
        Subscription $subscription,
    ): void {
        try {
            foreach ($subscription as $message) {
                $this->deliver($state, $message);
            }
        } catch (Throwable $e) {
            if (!$state->cancelled) {
                $this->logger->error("Broadcast channel '$state->name' subscription failed", ['exception' => $e]);
            }
        }

        if ($state->cancelled) {
            return;
        }

        $this->logger->error(
            "Broadcast channel '$state->name' subscription ended unexpectedly; closing its streams so clients reconnect",
        );

        $this->cancel($state);

        foreach ($state->connections as $connection) {
            $connection->close();
        }
    }

    private function deliver(
        ChannelState $state,
        Message $message,
    ): void {
        $decoded = json_decode($message->payload, false);

        if (
            !$decoded instanceof stdClass
            || !is_string($decoded->event ?? null)
            || !is_string($decoded->id ?? null)
            || !property_exists($decoded, 'data')
            || strpbrk($decoded->event . $decoded->id, "\r\n") !== false
            || $decoded->event === ''
            || $decoded->id === ''

        ) {
            $this->logger->error("Skipped malformed broadcast message on channel '$state->name'", [
                'payload' => substr($message->payload, 0, 200),
            ]);

            return;
        }

        $data = json_encode($decoded->data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($data === false) {
            $this->logger->error("Skipped broadcast message with unencodable data on channel '$state->name'");

            return;
        }

        $frame = $this->replayBuffer->record($state->name, $decoded->id, $decoded->event, $data)->toFrame();

        foreach ($state->connections as $connection) {
            $connection->send($frame);
        }
    }

    /**
     * @param list<ChannelState> $states
     */
    private function abandon(
        array $states,
    ): void {
        foreach ($states as $state) {
            $state->joining--;
            $this->closeIfUnused($state);
        }
    }

    private function closeIfUnused(
        ChannelState $state,
    ): void {
        if (
            $state->connections === []
            && $state->joining === 0
        ) {
            $this->cancel($state);
        }
    }

    private function cancel(
        ChannelState $state,
    ): void {
        if ($state->cancelled) {
            return;
        }

        $state->cancelled = true;

        if (($this->channels[$state->name] ?? null) === $state) {
            unset($this->channels[$state->name]);
        }

        $this->replayBuffer->dropChannel($state->name);

        try {
            $state->subscription?->cancel();
        } catch (Throwable $e) {
            $this->logger->warning(
                "Failed to cancel broadcast channel '$state->name' subscription",
                ['exception' => $e],
            );
        }
    }
}
