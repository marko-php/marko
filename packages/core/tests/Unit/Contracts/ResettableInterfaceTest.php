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

/*
 * Asserts only on the two phrases that ARE the contract — an implementor
 * reading nothing but this interface must not mistake reset() for a
 * destructive teardown. Deliberately does not assert on incidental prose
 * such as the example runtimes named in the docblock: those are free to be
 * reworded, and pinning them would make this test fail on edits that change
 * no behaviour and no contract.
 */
it('documents that reset is non destructive', function (): void {
    $reflection = new ReflectionClass(ResettableInterface::class);
    $classDoc = $reflection->getDocComment();
    $methodDoc = $reflection->getMethod('reset')->getDocComment();

    expect($classDoc)
        ->not->toBeFalse()
        ->and($classDoc)
        ->toContain('non-destructive')
        ->and($methodDoc)
        ->not->toBeFalse()
        ->and($methodDoc)
        ->toContain('not destroy');
});
