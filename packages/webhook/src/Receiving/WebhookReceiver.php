<?php

declare(strict_types=1);

namespace Marko\Webhook\Receiving;

use JsonException;
use Marko\Config\Exceptions\ConfigException;
use Marko\Routing\Http\Request;
use Marko\Webhook\Config\WebhookConfig;
use Marko\Webhook\Contracts\WebhookReceiverInterface;
use Marko\Webhook\Contracts\WebhookReplayGuardInterface;
use Marko\Webhook\Exceptions\InvalidSignatureException;
use Marko\Webhook\Exceptions\InvalidWebhookPayloadException;
use Marko\Webhook\Exceptions\InvalidWebhookSecretException;
use Marko\Webhook\Exceptions\WebhookPayloadTooLargeException;
use Marko\Webhook\WebhookSecret;
use Psr\Clock\ClockInterface;

class WebhookReceiver implements WebhookReceiverInterface
{
    public function __construct(
        private readonly WebhookVerifier $verifier,
        private readonly WebhookConfig $webhookConfig,
        private readonly ClockInterface $clock,
        private readonly ?WebhookReplayGuardInterface $replayGuard = null,
    ) {}

    /**
     * Checks, in order: the body size (before any hashing), the X-Webhook-Timestamp and X-Webhook-Id
     * headers, timestamp freshness, the signature, that the body is a JSON object or array, and, with
     * webhook.replay_protection on, that the X-Webhook-Id was not received before.
     *
     * @return array<string, mixed>
     *
     * @throws InvalidSignatureException|InvalidWebhookSecretException|InvalidWebhookPayloadException|ConfigException
     */
    public function receive(
        Request $request,
        string $secret,
    ): array {
        WebhookSecret::assertValid($secret);

        $body = $request->body();

        if (strlen($body) > $this->webhookConfig->maxBodyBytes) {
            throw WebhookPayloadTooLargeException::forBody(strlen($body), $this->webhookConfig->maxBodyBytes);
        }

        $signature = $request->header('X-Webhook-Signature') ?? '';
        $timestamp = $request->header('X-Webhook-Timestamp');
        $webhookId = $request->header('X-Webhook-Id');

        if ($timestamp === null) {
            throw InvalidSignatureException::missingTimestamp();
        }

        if (
            $webhookId === null
            || $webhookId === ''
        ) {
            throw InvalidSignatureException::missingWebhookId();
        }

        $ts = (int) $timestamp;
        $now = $this->clock->now()->getTimestamp();

        if (abs($now - $ts) > $this->webhookConfig->timestampTolerance) {
            throw InvalidSignatureException::staleTimestamp($ts, $this->webhookConfig->timestampTolerance, $now);
        }

        if (!$this->verifier->verify(
            $body,
            $timestamp,
            $signature,
            $secret,
            $this->webhookConfig->timestampTolerance,
            $webhookId,
        )) {
            throw InvalidSignatureException::forRequest();
        }

        $data = $this->decode($body);

        $this->claim($webhookId);

        return $data;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws InvalidWebhookPayloadException
     */
    private function decode(
        string $body,
    ): array {
        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw InvalidWebhookPayloadException::malformedJson($e);
        }

        if (!is_array($data)) {
            throw InvalidWebhookPayloadException::notAnArray(get_debug_type($data));
        }

        return $data;
    }

    /**
     * A delivery stays claimed for twice the tolerance: its timestamp is accepted from `tolerance` seconds
     * before to `tolerance` seconds after it was signed, so it cannot be replayed once the claim expires.
     *
     * @throws InvalidSignatureException|ConfigException
     */
    private function claim(
        string $webhookId,
    ): void {
        if (!$this->webhookConfig->replayProtection) {
            return;
        }

        if ($this->replayGuard === null) {
            throw new ConfigException(
                message: 'Configuration key "webhook.replay_protection" is true, but WebhookReceiver has no replay guard',
                context: 'While checking the X-Webhook-Id of an incoming webhook against recently received deliveries.',
                suggestion: 'Resolve WebhookReceiver from the container (it then gets the bound WebhookReplayGuardInterface, which needs marko/cache), pass a WebhookReplayGuardInterface to its constructor, or set webhook.replay_protection to false.',
            );
        }

        if (!$this->replayGuard->claim($webhookId, $this->webhookConfig->timestampTolerance * 2)) {
            throw InvalidSignatureException::replayed($webhookId);
        }
    }
}
