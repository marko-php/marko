<?php

declare(strict_types=1);

use Marko\Queue\JobInterface;

describe('JobInterface', function (): void {
    it('defines handle method', function (): void {
        $reflection = new ReflectionClass(JobInterface::class);

        expect($reflection->isInterface())->toBeTrue()
            ->and($reflection->hasMethod('handle'))->toBeTrue();

        $method = $reflection->getMethod('handle');

        expect($method->isPublic())->toBeTrue()
            ->and($method->getReturnType()?->getName())->toBe('void');
    });

    it('defines id methods', function (): void {
        $reflection = new ReflectionClass(JobInterface::class);

        expect($reflection->hasMethod('getId'))->toBeTrue()
            ->and($reflection->hasMethod('setId'))->toBeTrue();

        $getId = $reflection->getMethod('getId');

        expect($getId->isPublic())->toBeTrue()
            ->and($getId->getReturnType()?->allowsNull())->toBeTrue()
            ->and($getId->getReturnType()?->getName())->toBe('string');

        $setId = $reflection->getMethod('setId');

        expect($setId->isPublic())->toBeTrue()
            ->and($setId->getReturnType()?->getName())->toBe('void')
            ->and($setId->getParameters())->toHaveCount(1)
            ->and($setId->getParameters()[0]->getName())->toBe('id')
            ->and($setId->getParameters()[0]->getType()?->getName())->toBe('string');
    });

    it('defines attempt methods', function (): void {
        $reflection = new ReflectionClass(JobInterface::class);

        expect($reflection->hasMethod('getAttempts'))->toBeTrue()
            ->and($reflection->hasMethod('incrementAttempts'))->toBeTrue()
            ->and($reflection->hasMethod('getMaxAttempts'))->toBeTrue();

        $getAttempts = $reflection->getMethod('getAttempts');

        expect($getAttempts->isPublic())->toBeTrue()
            ->and($getAttempts->getReturnType()?->getName())->toBe('int');

        $incrementAttempts = $reflection->getMethod('incrementAttempts');

        expect($incrementAttempts->isPublic())->toBeTrue()
            ->and($incrementAttempts->getReturnType()?->getName())->toBe('void');

        $getMaxAttempts = $reflection->getMethod('getMaxAttempts');

        expect($getMaxAttempts->isPublic())->toBeTrue()
            ->and($getMaxAttempts->getReturnType()?->getName())->toBe('int');
    });

    it('defines serialization methods', function (): void {
        $reflection = new ReflectionClass(JobInterface::class);

        expect($reflection->hasMethod('serialize'))->toBeTrue()
            ->and($reflection->hasMethod('unserialize'))->toBeTrue();

        $serialize = $reflection->getMethod('serialize');

        expect($serialize->isPublic())->toBeTrue()
            ->and($serialize->getReturnType()?->getName())->toBe('string');

        $unserialize = $reflection->getMethod('unserialize');

        expect($unserialize->isPublic())->toBeTrue()
            ->and($unserialize->isStatic())->toBeTrue()
            ->and($unserialize->getReturnType()?->getName())->toBe('static')
            ->and($unserialize->getParameters())->toHaveCount(1)
            ->and($unserialize->getParameters()[0]->getName())->toBe('data')
            ->and($unserialize->getParameters()[0]->getType()?->getName())->toBe('string');
    });
});
