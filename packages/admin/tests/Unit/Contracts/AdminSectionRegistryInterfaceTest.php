<?php

declare(strict_types=1);

use Marko\Admin\Contracts\AdminSectionInterface;
use Marko\Admin\Contracts\AdminSectionRegistryInterface;

it('creates AdminSectionRegistryInterface with register, all, get methods', function (): void {
    $reflection = new ReflectionClass(AdminSectionRegistryInterface::class);

    expect($reflection->isInterface())->toBeTrue()
        ->and($reflection->hasMethod('register'))->toBeTrue()
        ->and($reflection->hasMethod('all'))->toBeTrue()
        ->and($reflection->hasMethod('get'))->toBeTrue();

    $register = $reflection->getMethod('register');
    $registerParams = $register->getParameters();
    expect($registerParams)->toHaveCount(1)
        ->and($registerParams[0]->getType()->getName())->toBe(AdminSectionInterface::class)
        ->and($register->getReturnType()->getName())->toBe('void');

    $all = $reflection->getMethod('all');
    expect($all->getReturnType()->getName())->toBe('array')
        ->and($all->getParameters())->toHaveCount(0);

    $get = $reflection->getMethod('get');
    $getParams = $get->getParameters();
    expect($getParams)->toHaveCount(1)
        ->and($getParams[0]->getType()->getName())->toBe('string')
        ->and($get->getReturnType()->getName())->toBe(AdminSectionInterface::class);
});
