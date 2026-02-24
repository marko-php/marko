<?php

declare(strict_types=1);

namespace Marko\Webhook\Tests\Contracts;

use Marko\Webhook\Contracts\WebhookDispatcherInterface;
use Marko\Webhook\Value\WebhookPayload;
use Marko\Webhook\Value\WebhookResponse;
use ReflectionMethod;

describe('WebhookDispatcherInterface', function (): void {
    it('defines WebhookDispatcherInterface with dispatch method', function (): void {
        expect(interface_exists(WebhookDispatcherInterface::class))->toBeTrue();

        $method = new ReflectionMethod(WebhookDispatcherInterface::class, 'dispatch');

        expect($method->getParameters())->toHaveCount(1)
            ->and($method->getParameters()[0]->getType()->getName())->toBe(WebhookPayload::class)
            ->and($method->getReturnType()->getName())->toBe(WebhookResponse::class);
    });
});
