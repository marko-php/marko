<?php

declare(strict_types=1);

namespace Marko\Errors\Tests\Unit\Contracts;

use Marko\Errors\Contracts\ErrorReporterInterface;
use Marko\Errors\ErrorReport;
use ReflectionClass;

describe('ErrorReporterInterface', function (): void {
    it('defines report method that accepts ErrorReport', function (): void {
        $reflection = new ReflectionClass(ErrorReporterInterface::class);

        expect($reflection->hasMethod('report'))->toBeTrue();

        $method = $reflection->getMethod('report');
        $parameters = $method->getParameters();

        expect($parameters)->toHaveCount(1);
        expect($parameters[0]->getName())->toBe('report');
        expect($parameters[0]->getType()?->getName())->toBe(ErrorReport::class);
    });

    it('defines report method that returns void', function (): void {
        $reflection = new ReflectionClass(ErrorReporterInterface::class);
        $method = $reflection->getMethod('report');
        $returnType = $method->getReturnType();

        expect($returnType)->not->toBeNull();
        expect($returnType->getName())->toBe('void');
    });

    it('defines shouldReport method that accepts ErrorReport', function (): void {
        $reflection = new ReflectionClass(ErrorReporterInterface::class);

        expect($reflection->hasMethod('shouldReport'))->toBeTrue();

        $method = $reflection->getMethod('shouldReport');
        $parameters = $method->getParameters();

        expect($parameters)->toHaveCount(1);
        expect($parameters[0]->getName())->toBe('report');
        expect($parameters[0]->getType()?->getName())->toBe(ErrorReport::class);
    });

    it('defines shouldReport method that returns bool', function (): void {
        $reflection = new ReflectionClass(ErrorReporterInterface::class);
        $method = $reflection->getMethod('shouldReport');
        $returnType = $method->getReturnType();

        expect($returnType)->not->toBeNull();
        expect($returnType->getName())->toBe('bool');
    });
});
