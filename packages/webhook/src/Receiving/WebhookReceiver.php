<?php

declare(strict_types=1);

namespace Marko\Webhook\Receiving;

use Marko\Routing\Http\Request;
use Marko\Webhook\Contracts\WebhookReceiverInterface;
use Marko\Webhook\Exceptions\InvalidSignatureException;

readonly class WebhookReceiver implements WebhookReceiverInterface
{
    public function __construct(
        private WebhookVerifier $verifier,
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @throws InvalidSignatureException
     */
    public function receive(
        Request $request,
        string $secret,
    ): array {
        $body = $request->body();
        $signature = $request->header('X-Webhook-Signature') ?? '';

        if (!$this->verifier->verify($body, $signature, $secret)) {
            throw InvalidSignatureException::forRequest();
        }

        return json_decode($body, true) ?? [];
    }
}
