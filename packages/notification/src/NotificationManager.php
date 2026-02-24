<?php

declare(strict_types=1);

namespace Marko\Notification;

use Marko\Notification\Contracts\ChannelInterface;
use Marko\Notification\Exceptions\NotificationException;

class NotificationManager
{
    /** @var array<string, ChannelInterface> */
    private array $channels = [];

    /**
     * Register a notification channel by name.
     */
    public function register(
        string $name,
        ChannelInterface $channel,
    ): void {
        $this->channels[$name] = $channel;
    }

    /**
     * Resolve a registered channel by name.
     *
     * @throws NotificationException When channel name is not registered
     */
    public function channel(
        string $name,
    ): ChannelInterface {
        if (!isset($this->channels[$name])) {
            throw NotificationException::unknownChannel($name);
        }

        return $this->channels[$name];
    }

    /**
     * Check whether a channel is registered.
     */
    public function hasChannel(
        string $name,
    ): bool {
        return isset($this->channels[$name]);
    }

    /**
     * Get all registered channel names.
     *
     * @return array<string>
     */
    public function getRegisteredChannels(): array
    {
        return array_keys($this->channels);
    }
}
