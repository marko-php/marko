<?php

declare(strict_types=1);

namespace Marko\PubSub;

use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;

readonly class PubSubConfig
{
    public function __construct(
        private ConfigRepositoryInterface $config,
    ) {}

    /**
     * @throws ConfigNotFoundException
     */
    public function driver(): string
    {
        return $this->config->getString('pubsub.driver');
    }

    /**
     * @throws ConfigNotFoundException
     */
    public function prefix(): string
    {
        return $this->config->getString('pubsub.prefix');
    }
}
