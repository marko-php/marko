<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Tests\Unit\Attributes;

use Attribute;
use Marko\AdminAuth\Attributes\RequiresPermission;
use ReflectionClass;

it('creates RequiresPermission attribute targeting methods with permission key property', function (): void {
    $reflection = new ReflectionClass(RequiresPermission::class);
    $attributes = $reflection->getAttributes(Attribute::class);

    expect($attributes)->toHaveCount(1);

    $attributeInstance = $attributes[0]->newInstance();
    expect($attributeInstance->flags)->toBe(Attribute::TARGET_METHOD);

    $permission = new RequiresPermission('posts.create');
    expect($permission->permission)->toBe('posts.create');
});
