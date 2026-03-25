<?php

declare(strict_types=1);

use Marko\Core\Attributes\After;
use Marko\Core\Attributes\Before;
use Marko\Core\Attributes\Plugin;

it('creates Plugin attribute with target class parameter', function (): void {
    $attribute = new Plugin(target: 'App\Services\UserService');

    expect($attribute->target)->toBe('App\Services\UserService');
});

it('creates Before attribute with optional sortOrder parameter', function (): void {
    $attribute = new Before(sortOrder: 10);

    expect($attribute->sortOrder)->toBe(10);
});

it('creates After attribute with optional sortOrder parameter', function (): void {
    $attribute = new After(sortOrder: 20);

    expect($attribute->sortOrder)->toBe(20);
});

it('creates Before attribute with optional method parameter', function (): void {
    $attribute = new Before(method: 'beforeSave');

    expect($attribute->method)->toBe('beforeSave');
});

it('creates After attribute with optional method parameter', function (): void {
    $attribute = new After(method: 'afterSave');

    expect($attribute->method)->toBe('afterSave');
});

it('defaults method to null when not specified on Before', function (): void {
    $attribute = new Before();

    expect($attribute->method)->toBeNull();
});

it('defaults method to null when not specified on After', function (): void {
    $attribute = new After();

    expect($attribute->method)->toBeNull();
});

it('creates Before attribute with both method and sortOrder parameters', function (): void {
    $attribute = new Before(sortOrder: 5, method: 'beforeSave');

    expect($attribute->sortOrder)->toBe(5)
        ->and($attribute->method)->toBe('beforeSave');
});

it('creates After attribute with both method and sortOrder parameters', function (): void {
    $attribute = new After(sortOrder: 15, method: 'afterSave');

    expect($attribute->sortOrder)->toBe(15)
        ->and($attribute->method)->toBe('afterSave');
});

it('defaults sortOrder to 0 when not specified', function (): void {
    $beforeAttribute = new Before();
    $afterAttribute = new After();

    expect($beforeAttribute->sortOrder)->toBe(0)
        ->and($afterAttribute->sortOrder)->toBe(0);
});
