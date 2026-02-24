<?php

declare(strict_types=1);

namespace Marko\Webhook\Tests\Attributes;

use Attribute;
use Marko\Webhook\Attributes\WebhookEndpoint;
use ReflectionClass;

describe('WebhookEndpoint', function (): void {
    it('defines WebhookEndpoint attribute for route registration', function (): void {
        $reflection = new ReflectionClass(WebhookEndpoint::class);
        $attributes = $reflection->getAttributes(Attribute::class);
        $attribute = $attributes[0]->newInstance();

        $endpoint = new WebhookEndpoint(path: '/webhooks/orders', secret: 'my-secret');

        expect($endpoint->path)->toBe('/webhooks/orders')
            ->and($endpoint->secret)->toBe('my-secret')
            ->and($attribute->flags)->toBe(Attribute::TARGET_METHOD);
    });
});
