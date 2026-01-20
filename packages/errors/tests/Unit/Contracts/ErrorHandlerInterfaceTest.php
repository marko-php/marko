<?php

declare(strict_types=1);

namespace Marko\Errors\Tests\Unit\Contracts;

use Marko\Errors\Contracts\ErrorHandlerInterface;
use Marko\Errors\ErrorReport;
use ReflectionMethod;
use Throwable;

describe('ErrorHandlerInterface', function (): void {
    it('defines handle method that accepts ErrorReport', function (): void {
        $reflection = new ReflectionMethod(ErrorHandlerInterface::class, 'handle');
        $parameters = $reflection->getParameters();

        expect($parameters)->toHaveCount(1);
        expect($parameters[0]->getName())->toBe('report');
        expect($parameters[0]->getType()->getName())->toBe(ErrorReport::class);
    });

    it('defines handle method that returns void', function (): void {
        $reflection = new ReflectionMethod(ErrorHandlerInterface::class, 'handle');
        $returnType = $reflection->getReturnType();

        expect($returnType)->not->toBeNull();
        expect($returnType->getName())->toBe('void');
    });

    it('defines handleException method that accepts Throwable', function (): void {
        $reflection = new ReflectionMethod(ErrorHandlerInterface::class, 'handleException');
        $parameters = $reflection->getParameters();

        expect($parameters)->toHaveCount(1);
        expect($parameters[0]->getName())->toBe('exception');
        expect($parameters[0]->getType()->getName())->toBe(Throwable::class);
    });

    it('defines handleError method for PHP errors with standard signature', function (): void {
        $reflection = new ReflectionMethod(ErrorHandlerInterface::class, 'handleError');
        $parameters = $reflection->getParameters();

        expect($parameters)->toHaveCount(4);

        expect($parameters[0]->getName())->toBe('level');
        expect($parameters[0]->getType()->getName())->toBe('int');

        expect($parameters[1]->getName())->toBe('message');
        expect($parameters[1]->getType()->getName())->toBe('string');

        expect($parameters[2]->getName())->toBe('file');
        expect($parameters[2]->getType()->getName())->toBe('string');

        expect($parameters[3]->getName())->toBe('line');
        expect($parameters[3]->getType()->getName())->toBe('int');

        $returnType = $reflection->getReturnType();
        expect($returnType)->not->toBeNull();
        expect($returnType->getName())->toBe('bool');
    });

    it('defines register method to register with PHP handlers', function (): void {
        $reflection = new ReflectionMethod(ErrorHandlerInterface::class, 'register');
        $parameters = $reflection->getParameters();

        expect($parameters)->toHaveCount(0);

        $returnType = $reflection->getReturnType();
        expect($returnType)->not->toBeNull();
        expect($returnType->getName())->toBe('void');
    });

    it('defines unregister method to restore previous handlers', function (): void {
        $reflection = new ReflectionMethod(ErrorHandlerInterface::class, 'unregister');
        $parameters = $reflection->getParameters();

        expect($parameters)->toHaveCount(0);

        $returnType = $reflection->getReturnType();
        expect($returnType)->not->toBeNull();
        expect($returnType->getName())->toBe('void');
    });
});
