<?php

declare(strict_types=1);

use Marko\Admin\Contracts\MenuItemInterface;

it('defines MenuItemInterface with getId, getLabel, getUrl, getIcon, getSortOrder, getPermission methods', function (): void {
    $reflection = new ReflectionClass(MenuItemInterface::class);

    expect($reflection->isInterface())->toBeTrue()
        ->and($reflection->hasMethod('getId'))->toBeTrue()
        ->and($reflection->hasMethod('getLabel'))->toBeTrue()
        ->and($reflection->hasMethod('getUrl'))->toBeTrue()
        ->and($reflection->hasMethod('getIcon'))->toBeTrue()
        ->and($reflection->hasMethod('getSortOrder'))->toBeTrue()
        ->and($reflection->hasMethod('getPermission'))->toBeTrue();

    $getId = $reflection->getMethod('getId');
    expect($getId->getReturnType()->getName())->toBe('string');

    $getLabel = $reflection->getMethod('getLabel');
    expect($getLabel->getReturnType()->getName())->toBe('string');

    $getUrl = $reflection->getMethod('getUrl');
    expect($getUrl->getReturnType()->getName())->toBe('string');

    $getIcon = $reflection->getMethod('getIcon');
    expect($getIcon->getReturnType()->getName())->toBe('string');

    $getSortOrder = $reflection->getMethod('getSortOrder');
    expect($getSortOrder->getReturnType()->getName())->toBe('int');

    $getPermission = $reflection->getMethod('getPermission');
    expect($getPermission->getReturnType()->getName())->toBe('string');
});
