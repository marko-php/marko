<?php

declare(strict_types=1);

namespace Marko\Notification\Config;

use Marko\Config\ConfigRepositoryInterface;

readonly class NotificationConfig
{
    public function __construct(
        private ConfigRepositoryInterface $config,
    ) {}

    /**
     * Get the default notification channels.
     *
     * @return array<string>
     */
    public function channels(): array
    {
        return $this->config->getArray('notification.channels');
    }
}
