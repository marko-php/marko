<?php

declare(strict_types=1);

use Marko\PubSub\Subscription;

describe('Subscription', function (): void {
    it('defines Subscription interface extending IteratorAggregate with cancel method', function (): void {
        $reflection = new ReflectionClass(Subscription::class);

        expect($reflection->isInterface())->toBeTrue()
            ->and($reflection->implementsInterface(IteratorAggregate::class))->toBeTrue()
            ->and($reflection->hasMethod('cancel'))->toBeTrue()
            ->and($reflection->hasMethod('getIterator'))->toBeTrue();

        $cancelMethod = $reflection->getMethod('cancel');

        expect($cancelMethod->isPublic())->toBeTrue()
            ->and($cancelMethod->getReturnType()?->getName())->toBe('void');

        $iteratorMethod = $reflection->getMethod('getIterator');

        expect($iteratorMethod->isPublic())->toBeTrue()
            ->and($iteratorMethod->getReturnType()?->getName())->toBe(Generator::class);
    });
});
