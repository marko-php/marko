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

it('defaults sortOrder to 0 when not specified', function (): void {
    $beforeAttribute = new Before();
    $afterAttribute = new After();

    expect($beforeAttribute->sortOrder)->toBe(0)
        ->and($afterAttribute->sortOrder)->toBe(0);
});
