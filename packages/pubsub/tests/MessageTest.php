<?php

declare(strict_types=1);

use Marko\PubSub\Message;

describe('Message', function (): void {
    it('creates readonly Message value object with channel, payload, and optional pattern properties', function (): void {
        $reflection = new ReflectionClass(Message::class);

        expect($reflection->isReadOnly())->toBeTrue();

        $channelProp = $reflection->getProperty('channel');
        $payloadProp = $reflection->getProperty('payload');
        $patternProp = $reflection->getProperty('pattern');

        expect($channelProp->isPublic())->toBeTrue()
            ->and($channelProp->getType()?->getName())->toBe('string')
            ->and($payloadProp->isPublic())->toBeTrue()
            ->and($payloadProp->getType()?->getName())->toBe('string')
            ->and($patternProp->isPublic())->toBeTrue()
            ->and($patternProp->getType()?->allowsNull())->toBeTrue()
            ->and($patternProp->getType()?->getName())->toBe('string');
    });

    it('creates Message with all properties accessible', function (): void {
        $message = new Message(
            channel: 'notifications',
            payload: '{"event":"user.created"}',
            pattern: 'notif*',
        );

        expect($message->channel)->toBe('notifications')
            ->and($message->payload)->toBe('{"event":"user.created"}')
            ->and($message->pattern)->toBe('notif*');
    });
});
