<?php

declare(strict_types=1);

use Marko\Layout\Attributes\Component;

it('can be instantiated with only template parameter', function (): void {
    $attribute = new Component(template: 'header.latte');

    expect($attribute->template)->toBe('header.latte');
});

it('can be instantiated with all parameters', function (): void {
    $attribute = new Component(
        template: 'header.latte',
        slot: 'header',
        handle: 'customer_*',
        slots: ['sidebar' => 'sidebar.latte'],
        sortOrder: 10,
        before: 'App\Components\OtherComponent',
        after: 'App\Components\AnotherComponent',
    );

    expect($attribute->template)->toBe('header.latte')
        ->and($attribute->slot)->toBe('header')
        ->and($attribute->handle)->toBe('customer_*')
        ->and($attribute->slots)->toBe(['sidebar' => 'sidebar.latte'])
        ->and($attribute->sortOrder)->toBe(10)
        ->and($attribute->before)->toBe('App\Components\OtherComponent')
        ->and($attribute->after)->toBe('App\Components\AnotherComponent');
});

it('accepts a string handle for wildcard prefix matching', function (): void {
    $attribute = new Component(template: 'header.latte', handle: 'customer_*');

    expect($attribute->handle)->toBe('customer_*');
});

it('accepts an array handle with controller class and method', function (): void {
    $handle = ['App\Controllers\ProductController', 'show'];
    $attribute = new Component(template: 'product.latte', handle: $handle);

    expect($attribute->handle)->toBe($handle);
});

it('accepts an array of handles for multiple page targeting', function (): void {
    $handles = ['customer_*', 'admin_*'];
    $attribute = new Component(template: 'nav.latte', handle: $handles);

    expect($attribute->handle)->toBe($handles);
});

it('accepts a slots array for nested composition', function (): void {
    $slots = ['sidebar' => 'sidebar', 'footer.links' => 'footer.links'];
    $attribute = new Component(template: 'page.latte', slots: $slots);

    expect($attribute->slots)->toBe($slots);
});

it('defaults sortOrder to 0', function (): void {
    $attribute = new Component(template: 'header.latte');

    expect($attribute->sortOrder)->toBe(0);
});

it('defaults handle to default', function (): void {
    $attribute = new Component(template: 'header.latte');

    expect($attribute->handle)->toBe('default');
});

it('defaults slot to null', function (): void {
    $attribute = new Component(template: 'header.latte');

    expect($attribute->slot)->toBeNull();
});

it('defaults slots to empty array', function (): void {
    $attribute = new Component(template: 'header.latte');

    expect($attribute->slots)->toBeEmpty();
});

it('defaults before and after to null', function (): void {
    $attribute = new Component(template: 'header.latte');

    expect($attribute->before)->toBeNull()
        ->and($attribute->after)->toBeNull();
});

it('targets classes only', function (): void {
    $reflection = new ReflectionClass(Component::class);
    $attributes = $reflection->getAttributes(Attribute::class);

    expect($attributes)->toHaveCount(1)
        ->and($attributes[0]->newInstance()->flags)->toBe(Attribute::TARGET_CLASS);
});
