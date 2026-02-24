<?php

declare(strict_types=1);

namespace Marko\Webhook\Tests\Contracts;

use Marko\Routing\Http\Request;
use Marko\Webhook\Contracts\WebhookReceiverInterface;
use ReflectionMethod;

describe('WebhookReceiverInterface', function (): void {
    it('defines WebhookReceiverInterface with receive method', function (): void {
        $reflection = new ReflectionMethod(WebhookReceiverInterface::class, 'receive');

        expect($reflection->getName())->toBe('receive')
            ->and($reflection->getParameters())->toHaveCount(2)
            ->and($reflection->getParameters()[0]->getName())->toBe('request')
            ->and($reflection->getParameters()[0]->getType()->getName())->toBe(Request::class)
            ->and($reflection->getParameters()[1]->getName())->toBe('secret')
            ->and($reflection->getParameters()[1]->getType()->getName())->toBe('string');
    });
});
