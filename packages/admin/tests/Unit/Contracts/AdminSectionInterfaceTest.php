<?php

declare(strict_types=1);

use Marko\Admin\Contracts\AdminSectionInterface;

it('defines AdminSectionInterface with getId, getLabel, getIcon, getSortOrder, getMenuItems methods', function (): void {
    $reflection = new ReflectionClass(AdminSectionInterface::class);

    expect($reflection->isInterface())->toBeTrue()
        ->and($reflection->hasMethod('getId'))->toBeTrue()
        ->and($reflection->hasMethod('getLabel'))->toBeTrue()
        ->and($reflection->hasMethod('getIcon'))->toBeTrue()
        ->and($reflection->hasMethod('getSortOrder'))->toBeTrue()
        ->and($reflection->hasMethod('getMenuItems'))->toBeTrue();

    $getId = $reflection->getMethod('getId');
    expect($getId->getReturnType()->getName())->toBe('string');

    $getLabel = $reflection->getMethod('getLabel');
    expect($getLabel->getReturnType()->getName())->toBe('string');

    $getIcon = $reflection->getMethod('getIcon');
    expect($getIcon->getReturnType()->getName())->toBe('string');

    $getSortOrder = $reflection->getMethod('getSortOrder');
    expect($getSortOrder->getReturnType()->getName())->toBe('int');

    $getMenuItems = $reflection->getMethod('getMenuItems');
    expect($getMenuItems->getReturnType()->getName())->toBe('array');
});
