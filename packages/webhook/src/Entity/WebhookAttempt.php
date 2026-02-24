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

    #[Column(name: 'status_code')]
    public ?int $statusCode = null;

    #[Column(name: 'response_body', type: 'TEXT')]
    public ?string $responseBody = null;

    #[Column(name: 'error_message', type: 'TEXT')]
    public ?string $errorMessage = null;

    #[Column(name: 'attempted_at')]
    public ?string $attemptedAt = null;

    public function __construct(
        #[Column(name: 'webhook_url')]
        public string $webhookUrl = '',
        #[Column]
        public string $event = '',
        #[Column(name: 'attempt_number')]
        public int $attemptNumber = 1,
    ) {}
}
