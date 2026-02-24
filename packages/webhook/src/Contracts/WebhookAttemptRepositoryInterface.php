<?php

declare(strict_types=1);

namespace Marko\Webhook\Contracts;

use Marko\Webhook\Entity\WebhookAttempt;

interface WebhookAttemptRepositoryInterface
{
    public function save(
        WebhookAttempt $attempt,
    ): WebhookAttempt;
}
