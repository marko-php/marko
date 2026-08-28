<?php

declare(strict_types=1);

namespace Marko\Core\Tests\Unit\Contracts;

use Marko\Core\Contracts\ResettableInterface;
use ReflectionClass;

it('defines a contract for clearing request scoped state', function (): void {
    $reflection = new ReflectionClass(ResettableInterface::class);

    expect($reflection->isInterface())
        ->toBeTrue()
        ->and($reflection->hasMethod('reset'))
        ->toBeTrue();
});

it('can be implemented by a class that clears its per request state', function (): void {
    $service = new class () implements ResettableInterface
    {
        public string $state = 'request-scoped';

        public function reset(): void
        {
            $this->state = '';
        }
    };

    $service->reset();

    expect($service)
        ->toBeInstanceOf(ResettableInterface::class)
        ->and($service->state)
        ->toBe('');
});

it('documents that reset is non destructive', function (): void {
    $classDoc = (new ReflectionClass(ResettableInterface::class))->getDocComment();
    $methodDoc = (new ReflectionClass(ResettableInterface::class))->getMethod('reset')->getDocComment();

    expect($classDoc)
        ->not->toBeFalse()
        ->and($classDoc)
        ->toContain('non-destructive')
        ->and($classDoc)
        ->toContain('PHP-FPM')
        ->and($methodDoc)
        ->not->toBeFalse()
        ->and($methodDoc)
        ->toContain('not destroy');
});
