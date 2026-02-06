<?php

declare(strict_types=1);

use Marko\Admin\Contracts\DashboardWidgetInterface;

it('defines DashboardWidgetInterface with getId, getLabel, getSortOrder, render methods', function (): void {
    $reflection = new ReflectionClass(DashboardWidgetInterface::class);

    expect($reflection->isInterface())->toBeTrue()
        ->and($reflection->hasMethod('getId'))->toBeTrue()
        ->and($reflection->hasMethod('getLabel'))->toBeTrue()
        ->and($reflection->hasMethod('getSortOrder'))->toBeTrue()
        ->and($reflection->hasMethod('render'))->toBeTrue();

    $getId = $reflection->getMethod('getId');
    expect($getId->getReturnType()->getName())->toBe('string');

    $getLabel = $reflection->getMethod('getLabel');
    expect($getLabel->getReturnType()->getName())->toBe('string');

    $getSortOrder = $reflection->getMethod('getSortOrder');
    expect($getSortOrder->getReturnType()->getName())->toBe('int');

    $render = $reflection->getMethod('render');
    expect($render->getReturnType()->getName())->toBe('string');
});
