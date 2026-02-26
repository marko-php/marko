<?php

declare(strict_types=1);

namespace Marko\PubSub;

interface PublisherInterface
{
    /**
     * Publish a message to a channel.
     */
    public function publish(string $channel, Message $message): void;
}
