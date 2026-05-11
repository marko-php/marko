<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Entity;

use Marko\Database\Entity\EntityExtension;
use ReflectionClass;

it('rejects direct instantiation as an abstract class', function (): void {
    $reflection = new ReflectionClass(EntityExtension::class);

    expect($reflection->isAbstract())->toBeTrue();
});

it('can be extended to create a concrete extension class', function (): void {
    $extension = new class () extends EntityExtension
    {
        public string $extraField;
    };

    $extension->extraField = 'value';

    expect($extension)->toBeInstanceOf(EntityExtension::class)
        ->and($extension->extraField)->toBe('value');
});
