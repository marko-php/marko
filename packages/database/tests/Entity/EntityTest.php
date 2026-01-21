<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Entity;

use Marko\Database\Entity\Entity;

it('creates Entity base class that can be extended', function (): void {
    $entity = new class () extends Entity
    {
        public int $id;

        public string $name;
    };

    $entity->id = 1;
    $entity->name = 'Test';

    expect($entity)
        ->toBeInstanceOf(Entity::class)
        ->and($entity->id)->toBe(1)
        ->and($entity->name)->toBe('Test');
});
