<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Entity;

use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityExtension;

it('returns null for an extension that has not been set', function (): void {
    $entity = new class () extends Entity {};

    $extensionClass = new class () extends EntityExtension {};

    expect($entity->extension($extensionClass::class))->toBeNull();
});

it('returns the extension instance after it is set', function (): void {
    $entity = new class () extends Entity {};

    $extension = new class () extends EntityExtension
    {
        public string $value = 'hello';
    };

    $entity->setExtension($extension);

    expect($entity->extension($extension::class))->toBe($extension);
});

it('overwrites a previously set extension of the same class', function (): void {
    $entity = new class () extends Entity {};

    $extensionA = new class () extends EntityExtension
    {
        public string $value = 'first';
    };

    $extensionB = clone $extensionA;
    $extensionB->value = 'second';

    $entity->setExtension($extensionA);
    $entity->setExtension($extensionB);

    expect($entity->extension($extensionA::class))->toBe($extensionB);
});

it('stores multiple extensions independently by class', function (): void {
    $entity = new class () extends Entity {};

    $extensionOne = new class () extends EntityExtension
    {
        public string $label = 'one';
    };

    $extensionTwo = new class () extends EntityExtension
    {
        public string $label = 'two';
    };

    $entity->setExtension($extensionOne);
    $entity->setExtension($extensionTwo);

    expect($entity->extension($extensionOne::class))->toBe($extensionOne)
        ->and($entity->extension($extensionTwo::class))->toBe($extensionTwo);
});
