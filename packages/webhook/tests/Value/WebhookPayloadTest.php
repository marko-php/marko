<?php

declare(strict_types=1);

namespace Marko\Webhook\Tests\Value;

use Marko\Webhook\Value\WebhookPayload;

describe('WebhookPayload', function (): void {
    it('creates WebhookPayload value object with url, event, data, and secret', function (): void {
        $payload = new WebhookPayload(
            url: 'https://example.com/webhook',
            event: 'order.created',
            data: ['order_id' => 123, 'total' => 99.99],
            secret: 'my-secret',
        );

        expect($payload->url)->toBe('https://example.com/webhook')
            ->and($payload->event)->toBe('order.created')
            ->and($payload->data)->toBe(['order_id' => 123, 'total' => 99.99])
            ->and($payload->secret)->toBe('my-secret');
    });
});
