<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Entity;

use Marko\Database\Entity\EntityExtensionRegistry;

it('registers an extension class against its target entity class', function (): void {
    $registry = new EntityExtensionRegistry();
    $registry->register('App\SomeEntity', 'App\SomeExtension');

    expect($registry->getExtensions('App\SomeEntity'))->toBe(['App\SomeExtension']);
});

it('returns all registered extension classes for an entity', function (): void {
    $registry = new EntityExtensionRegistry();
    $registry->register('App\SomeEntity', 'App\ExtensionA');
    $registry->register('App\SomeEntity', 'App\ExtensionB');

    expect($registry->getExtensions('App\SomeEntity'))->toBe(['App\ExtensionA', 'App\ExtensionB']);
});

it('returns empty array for entity with no registered extensions', function (): void {
    $registry = new EntityExtensionRegistry();

    expect($registry->getExtensions('App\SomeEntity'))->toBeEmpty();
});

it('is idempotent when registering the same pair twice', function (): void {
    $registry = new EntityExtensionRegistry();
    $registry->register('App\SomeEntity', 'App\SomeExtension');
    $registry->register('App\SomeEntity', 'App\SomeExtension');

    expect($registry->getExtensions('App\SomeEntity'))->toBe(['App\SomeExtension']);
});
