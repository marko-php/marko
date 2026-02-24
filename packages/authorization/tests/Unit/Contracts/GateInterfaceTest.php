<?php

declare(strict_types=1);

namespace Marko\Authorization\Tests\Unit\Contracts;

use Marko\Authorization\Contracts\GateInterface;
use ReflectionClass;

it('defines GateInterface with define, allows, denies, and authorize methods', function (): void {
    $reflection = new ReflectionClass(GateInterface::class);

    expect($reflection->isInterface())->toBeTrue()
        ->and($reflection->hasMethod('define'))->toBeTrue()
        ->and($reflection->hasMethod('allows'))->toBeTrue()
        ->and($reflection->hasMethod('denies'))->toBeTrue()
        ->and($reflection->hasMethod('authorize'))->toBeTrue();
});

it('defines GateInterface with policy registration method', function (): void {
    $reflection = new ReflectionClass(GateInterface::class);

    expect($reflection->hasMethod('policy'))->toBeTrue();
});
