<?php

declare(strict_types=1);

namespace Marko\Webhook\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table(name: 'webhook_attempts')]
class WebhookAttempt extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column]
    public ?int $statusCode = null;

    #[Column(type: 'TEXT')]
    public ?string $responseBody = null;

    #[Column(type: 'TEXT')]
    public ?string $errorMessage = null;

    #[Column]
    public ?string $attemptedAt = null;

    public function __construct(
        #[Column]
        public string $webhookUrl = '',
        #[Column]
        public string $event = '',
        #[Column]
        public int $attemptNumber = 1,
    ) {}
}
