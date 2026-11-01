<?php

declare(strict_types=1);

use Marko\Queue\WorkerInterface;
use Marko\Queue\WorkerOptions;
use Marko\Queue\WorkerStopReason;

describe('WorkerInterface', function () {
    test('defines work method', function () {
        $reflection = new ReflectionClass(WorkerInterface::class);

        expect($reflection->hasMethod('work'))->toBeTrue();

        $method = $reflection->getMethod('work');

        expect($method->isPublic())->toBeTrue();

        $parameters = $method->getParameters();

        expect($parameters)->toHaveCount(4)
            ->and($parameters[0]->getName())->toBe('queues')
            ->and($parameters[0]->getType()?->getName())->toBe('array')
            ->and($parameters[0]->allowsNull())->toBeTrue()
            ->and($parameters[1]->getName())->toBe('once')
            ->and($parameters[1]->getType()?->getName())->toBe('bool')
            ->and($parameters[2]->getName())->toBe('sleep')
            ->and($parameters[2]->getType()?->getName())->toBe('int')
            ->and($parameters[3]->getName())->toBe('options')
            ->and($parameters[3]->getType()?->getName())->toBe(WorkerOptions::class)
            ->and($parameters[3]->isDefaultValueAvailable())->toBeTrue();

        $returnType = $method->getReturnType();

        expect($returnType?->getName())->toBe(WorkerStopReason::class);
    });

    test('defines stop method', function () {
        $reflection = new ReflectionClass(WorkerInterface::class);

        expect($reflection->hasMethod('stop'))->toBeTrue();

        $method = $reflection->getMethod('stop');

        expect($method->isPublic())->toBeTrue()
            ->and($method->getParameters())->toBeEmpty();

        $returnType = $method->getReturnType();

        expect($returnType?->getName())->toBe('void');
    });
});
