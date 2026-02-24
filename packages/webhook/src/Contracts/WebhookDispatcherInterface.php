<?php

declare(strict_types=1);

namespace Marko\Webhook\Contracts;

use Marko\Webhook\Value\WebhookPayload;
use Marko\Webhook\Value\WebhookResponse;

interface WebhookDispatcherInterface
{
    public function dispatch(
        WebhookPayload $payload,
    ): WebhookResponse;
}
