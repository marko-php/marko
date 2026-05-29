<?php

declare(strict_types=1);

namespace Marko\DevServer\Detection;

use Marko\PubSub\PublisherInterface;

class PubSubDetector
{
    public function detect(): ?string
    {
        if (!$this->isPubSubInstalled()) {
            return null;
        }

        return 'marko pubsub:listen';
    }

    protected function isPubSubInstalled(): bool
    {
        return class_exists(PublisherInterface::class);
    }
}
