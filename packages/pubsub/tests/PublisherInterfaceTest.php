<?php

declare(strict_types=1);

use Marko\PubSub\Message;
use Marko\PubSub\PublisherInterface;

describe('PublisherInterface', function (): void {
    it('defines PublisherInterface with publish method accepting channel and Message', function (): void {
        $reflection = new ReflectionClass(PublisherInterface::class);

        expect($reflection->isInterface())->toBeTrue()
            ->and($reflection->hasMethod('publish'))->toBeTrue();

        $method = $reflection->getMethod('publish');

        expect($method->isPublic())->toBeTrue()
            ->and($method->getReturnType()?->getName())->toBe('void')
            ->and($method->getParameters())->toHaveCount(2);

        $channelParam = $method->getParameters()[0];

        expect($channelParam->getName())->toBe('channel')
            ->and($channelParam->getType()?->getName())->toBe('string');

        $messageParam = $method->getParameters()[1];

        expect($messageParam->getName())->toBe('message')
            ->and($messageParam->getType()?->getName())->toBe(Message::class);
    });
});
