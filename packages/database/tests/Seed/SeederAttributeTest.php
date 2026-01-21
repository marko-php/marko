<?php

declare(strict_types=1);

use Marko\Database\Seed\Seeder;

describe('Seeder Attribute', function (): void {
    it('defines #[Seeder] attribute with name and optional order', function (): void {
        $reflection = new ReflectionClass(Seeder::class);

        expect($reflection->getAttributes(Attribute::class))->toHaveCount(1);

        $attribute = $reflection->getAttributes(Attribute::class)[0]->newInstance();
        expect($attribute->flags)->toBe(Attribute::TARGET_CLASS);

        // Verify constructor parameters
        $constructor = $reflection->getConstructor();
        expect($constructor)->not->toBeNull();

        $params = $constructor->getParameters();
        expect($params)->toHaveCount(2);

        // First param: name (required)
        expect($params[0]->getName())->toBe('name');
        expect($params[0]->getType()?->getName())->toBe('string');
        expect($params[0]->isDefaultValueAvailable())->toBeFalse();

        // Second param: order (optional, default 0)
        expect($params[1]->getName())->toBe('order');
        expect($params[1]->getType()?->getName())->toBe('int');
        expect($params[1]->isDefaultValueAvailable())->toBeTrue();
        expect($params[1]->getDefaultValue())->toBe(0);
    });

    it('can be instantiated with name only', function (): void {
        $seeder = new Seeder(name: 'users');

        expect($seeder->name)->toBe('users');
        expect($seeder->order)->toBe(0);
    });

    it('can be instantiated with name and order', function (): void {
        $seeder = new Seeder(name: 'posts', order: 10);

        expect($seeder->name)->toBe('posts');
        expect($seeder->order)->toBe(10);
    });
});
