<?php

declare(strict_types=1);

use Marko\Queue\WorkerInterface;

describe('WorkerInterface', function () {
    test('defines work method', function () {
        $reflection = new ReflectionClass(WorkerInterface::class);

        expect($reflection->hasMethod('work'))->toBeTrue();

        $method = $reflection->getMethod('work');

        expect($method->isPublic())->toBeTrue();

        $parameters = $method->getParameters();

        expect($parameters)->toHaveCount(3);
        expect($parameters[0]->getName())->toBe('queue');
        expect($parameters[0]->getType()?->getName())->toBe('string');
        expect($parameters[0]->allowsNull())->toBeTrue();
        expect($parameters[1]->getName())->toBe('once');
        expect($parameters[1]->getType()?->getName())->toBe('bool');
        expect($parameters[2]->getName())->toBe('sleep');
        expect($parameters[2]->getType()?->getName())->toBe('int');

        $returnType = $method->getReturnType();

        expect($returnType?->getName())->toBe('void');
    });

    test('defines stop method', function () {
        $reflection = new ReflectionClass(WorkerInterface::class);

        expect($reflection->hasMethod('stop'))->toBeTrue();

        $method = $reflection->getMethod('stop');

        expect($method->isPublic())->toBeTrue();
        expect($method->getParameters())->toBeEmpty();

        $returnType = $method->getReturnType();

        expect($returnType?->getName())->toBe('void');
    });
});
