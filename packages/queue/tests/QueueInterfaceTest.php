<?php

declare(strict_types=1);

use Marko\Queue\JobInterface;
use Marko\Queue\QueueInterface;

describe('QueueInterface', function (): void {
    it('defines push method', function (): void {
        $reflection = new ReflectionClass(QueueInterface::class);

        expect($reflection->isInterface())->toBeTrue()
            ->and($reflection->hasMethod('push'))->toBeTrue();

        $method = $reflection->getMethod('push');

        expect($method->isPublic())->toBeTrue()
            ->and($method->getReturnType()?->getName())->toBe('string')
            ->and($method->getParameters())->toHaveCount(2);

        $jobParam = $method->getParameters()[0];

        expect($jobParam->getName())->toBe('job')
            ->and($jobParam->getType()?->getName())->toBe(JobInterface::class);

        $queueParam = $method->getParameters()[1];

        expect($queueParam->getName())->toBe('queue')
            ->and($queueParam->getType()?->allowsNull())->toBeTrue()
            ->and($queueParam->getType()?->getName())->toBe('string')
            ->and($queueParam->isDefaultValueAvailable())->toBeTrue()
            ->and($queueParam->getDefaultValue())->toBeNull();
    });

    it('defines later method', function (): void {
        $reflection = new ReflectionClass(QueueInterface::class);

        expect($reflection->hasMethod('later'))->toBeTrue();

        $method = $reflection->getMethod('later');

        expect($method->isPublic())->toBeTrue()
            ->and($method->getReturnType()?->getName())->toBe('string')
            ->and($method->getParameters())->toHaveCount(3);

        $delayParam = $method->getParameters()[0];

        expect($delayParam->getName())->toBe('delay')
            ->and($delayParam->getType()?->getName())->toBe('int');

        $jobParam = $method->getParameters()[1];

        expect($jobParam->getName())->toBe('job')
            ->and($jobParam->getType()?->getName())->toBe(JobInterface::class);

        $queueParam = $method->getParameters()[2];

        expect($queueParam->getName())->toBe('queue')
            ->and($queueParam->getType()?->allowsNull())->toBeTrue()
            ->and($queueParam->getType()?->getName())->toBe('string')
            ->and($queueParam->isDefaultValueAvailable())->toBeTrue()
            ->and($queueParam->getDefaultValue())->toBeNull();
    });

    it('defines pop method', function (): void {
        $reflection = new ReflectionClass(QueueInterface::class);

        expect($reflection->hasMethod('pop'))->toBeTrue();

        $method = $reflection->getMethod('pop');

        expect($method->isPublic())->toBeTrue()
            ->and($method->getReturnType()?->allowsNull())->toBeTrue()
            ->and($method->getReturnType()?->getName())->toBe(JobInterface::class)
            ->and($method->getParameters())->toHaveCount(1);

        $queueParam = $method->getParameters()[0];

        expect($queueParam->getName())->toBe('queue')
            ->and($queueParam->getType()?->allowsNull())->toBeTrue()
            ->and($queueParam->getType()?->getName())->toBe('string')
            ->and($queueParam->isDefaultValueAvailable())->toBeTrue()
            ->and($queueParam->getDefaultValue())->toBeNull();
    });

    it('defines size method', function (): void {
        $reflection = new ReflectionClass(QueueInterface::class);

        expect($reflection->hasMethod('size'))->toBeTrue();

        $method = $reflection->getMethod('size');

        expect($method->isPublic())->toBeTrue()
            ->and($method->getReturnType()?->getName())->toBe('int')
            ->and($method->getParameters())->toHaveCount(1);

        $queueParam = $method->getParameters()[0];

        expect($queueParam->getName())->toBe('queue')
            ->and($queueParam->getType()?->allowsNull())->toBeTrue()
            ->and($queueParam->getType()?->getName())->toBe('string')
            ->and($queueParam->isDefaultValueAvailable())->toBeTrue()
            ->and($queueParam->getDefaultValue())->toBeNull();
    });

    it('defines clear method', function (): void {
        $reflection = new ReflectionClass(QueueInterface::class);

        expect($reflection->hasMethod('clear'))->toBeTrue();

        $method = $reflection->getMethod('clear');

        expect($method->isPublic())->toBeTrue()
            ->and($method->getReturnType()?->getName())->toBe('int')
            ->and($method->getParameters())->toHaveCount(1);

        $queueParam = $method->getParameters()[0];

        expect($queueParam->getName())->toBe('queue')
            ->and($queueParam->getType()?->allowsNull())->toBeTrue()
            ->and($queueParam->getType()?->getName())->toBe('string')
            ->and($queueParam->isDefaultValueAvailable())->toBeTrue()
            ->and($queueParam->getDefaultValue())->toBeNull();
    });

    it('defines delete method', function (): void {
        $reflection = new ReflectionClass(QueueInterface::class);

        expect($reflection->hasMethod('delete'))->toBeTrue();

        $method = $reflection->getMethod('delete');

        expect($method->isPublic())->toBeTrue()
            ->and($method->getReturnType()?->getName())->toBe('bool')
            ->and($method->getParameters())->toHaveCount(1);

        $jobIdParam = $method->getParameters()[0];

        expect($jobIdParam->getName())->toBe('jobId')
            ->and($jobIdParam->getType()?->getName())->toBe('string');
    });

    it('defines release method', function (): void {
        $reflection = new ReflectionClass(QueueInterface::class);

        expect($reflection->hasMethod('release'))->toBeTrue();

        $method = $reflection->getMethod('release');

        expect($method->isPublic())->toBeTrue()
            ->and($method->getReturnType()?->getName())->toBe('bool')
            ->and($method->getParameters())->toHaveCount(2);

        $jobIdParam = $method->getParameters()[0];

        expect($jobIdParam->getName())->toBe('jobId')
            ->and($jobIdParam->getType()?->getName())->toBe('string');

        $delayParam = $method->getParameters()[1];

        expect($delayParam->getName())->toBe('delay')
            ->and($delayParam->getType()?->getName())->toBe('int')
            ->and($delayParam->isDefaultValueAvailable())->toBeTrue()
            ->and($delayParam->getDefaultValue())->toBe(0);
    });
});
