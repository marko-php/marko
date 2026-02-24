<?php

declare(strict_types=1);

namespace Marko\Webhook\Config;

use Marko\Config\ConfigRepositoryInterface;

readonly class WebhookConfig
{
    public int $timeout;

    public int $maxRetries;

    public int $retryDelay;

    public function __construct(
        ConfigRepositoryInterface $config,
    ) {
        $this->timeout = $config->getInt('webhook.timeout');
        $this->maxRetries = $config->getInt('webhook.max_retries');
        $this->retryDelay = $config->getInt('webhook.retry_delay');
    }
}
