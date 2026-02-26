<?php

declare(strict_types=1);

use Marko\PubSub\SubscriberInterface;
use Marko\PubSub\Subscription;

describe('SubscriberInterface', function (): void {
    it('defines SubscriberInterface with subscribe method accepting variadic channels returning Subscription', function (): void {
        $reflection = new ReflectionClass(SubscriberInterface::class);

        expect($reflection->isInterface())->toBeTrue()
            ->and($reflection->hasMethod('subscribe'))->toBeTrue();

        $method = $reflection->getMethod('subscribe');

        expect($method->isPublic())->toBeTrue()
            ->and($method->getReturnType()?->getName())->toBe(Subscription::class)
            ->and($method->getParameters())->toHaveCount(1);

        $channelsParam = $method->getParameters()[0];

        expect($channelsParam->getName())->toBe('channels')
            ->and($channelsParam->getType()?->getName())->toBe('string')
            ->and($channelsParam->isVariadic())->toBeTrue();
    });

    it('defines SubscriberInterface with psubscribe method accepting variadic patterns returning Subscription', function (): void {
        $reflection = new ReflectionClass(SubscriberInterface::class);

        expect($reflection->isInterface())->toBeTrue()
            ->and($reflection->hasMethod('psubscribe'))->toBeTrue();

        $method = $reflection->getMethod('psubscribe');

        expect($method->isPublic())->toBeTrue()
            ->and($method->getReturnType()?->getName())->toBe(Subscription::class)
            ->and($method->getParameters())->toHaveCount(1);

        $patternsParam = $method->getParameters()[0];

        expect($patternsParam->getName())->toBe('patterns')
            ->and($patternsParam->getType()?->getName())->toBe('string')
            ->and($patternsParam->isVariadic())->toBeTrue();
    });
});
