<?php

declare(strict_types=1);

namespace Marko\Webhook\Contracts;

use Marko\Routing\Http\Request;

interface WebhookReceiverInterface
{
    /**
     * @return array<string, mixed>
     */
    public function receive(
        Request $request,
        string $secret,
    ): array;
}
