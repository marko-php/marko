<?php

declare(strict_types=1);

use Marko\Queue\FailedJob;
use Marko\Queue\FailedJobRepositoryInterface;

describe('FailedJobRepositoryInterface', function (): void {
    it('defines store method', function (): void {
        $reflection = new ReflectionClass(FailedJobRepositoryInterface::class);

        expect($reflection->isInterface())->toBeTrue()
            ->and($reflection->hasMethod('store'))->toBeTrue();

        $method = $reflection->getMethod('store');

        expect($method->isPublic())->toBeTrue()
            ->and($method->getNumberOfParameters())->toBe(1);

        $params = $method->getParameters();

        expect($params[0]->getName())->toBe('failedJob')
            ->and($params[0]->getType()->getName())->toBe(FailedJob::class);

        $returnType = $method->getReturnType();

        expect($returnType->getName())->toBe('void');
    });

    it('defines all method', function (): void {
        $reflection = new ReflectionClass(FailedJobRepositoryInterface::class);

        expect($reflection->hasMethod('all'))->toBeTrue();

        $method = $reflection->getMethod('all');

        expect($method->isPublic())->toBeTrue()
            ->and($method->getNumberOfParameters())->toBe(0);

        $returnType = $method->getReturnType();

        expect($returnType->getName())->toBe('array');
    });

    it('defines find method', function (): void {
        $reflection = new ReflectionClass(FailedJobRepositoryInterface::class);

        expect($reflection->hasMethod('find'))->toBeTrue();

        $method = $reflection->getMethod('find');

        expect($method->isPublic())->toBeTrue()
            ->and($method->getNumberOfParameters())->toBe(1);

        $params = $method->getParameters();

        expect($params[0]->getName())->toBe('id')
            ->and($params[0]->getType()->getName())->toBe('string');

        $returnType = $method->getReturnType();

        expect($returnType->allowsNull())->toBeTrue()
            ->and($returnType->getName())->toBe(FailedJob::class);
    });

    it('defines delete method', function (): void {
        $reflection = new ReflectionClass(FailedJobRepositoryInterface::class);

        expect($reflection->hasMethod('delete'))->toBeTrue();

        $method = $reflection->getMethod('delete');

        expect($method->isPublic())->toBeTrue()
            ->and($method->getNumberOfParameters())->toBe(1);

        $params = $method->getParameters();

        expect($params[0]->getName())->toBe('id')
            ->and($params[0]->getType()->getName())->toBe('string');

        $returnType = $method->getReturnType();

        expect($returnType->getName())->toBe('bool');
    });

    it('defines clear method', function (): void {
        $reflection = new ReflectionClass(FailedJobRepositoryInterface::class);

        expect($reflection->hasMethod('clear'))->toBeTrue();

        $method = $reflection->getMethod('clear');

        expect($method->isPublic())->toBeTrue()
            ->and($method->getNumberOfParameters())->toBe(0);

        $returnType = $method->getReturnType();

        expect($returnType->getName())->toBe('int');
    });

    it('defines count method', function (): void {
        $reflection = new ReflectionClass(FailedJobRepositoryInterface::class);

        expect($reflection->hasMethod('count'))->toBeTrue();

        $method = $reflection->getMethod('count');

        expect($method->isPublic())->toBeTrue()
            ->and($method->getNumberOfParameters())->toBe(0);

        $returnType = $method->getReturnType();

        expect($returnType->getName())->toBe('int');
    });
});
