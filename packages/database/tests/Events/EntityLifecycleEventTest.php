<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Events;

use Marko\Core\Event\Event;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Marko\Database\Events\EntityCreated;
use Marko\Database\Events\EntityCreating;
use Marko\Database\Events\EntityDeleted;
use Marko\Database\Events\EntityDeleting;
use Marko\Database\Events\EntityLifecycleEvent;
use Marko\Database\Events\EntityUpdated;
use Marko\Database\Events\EntityUpdating;
use ReflectionClass;

#[Table('lifecycle_items')]
class LifecycleItem extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column]
    public string $name;
}

it('creates abstract EntityLifecycleEvent base class with entity and entityClass properties', function (): void {
    $reflection = new ReflectionClass(EntityLifecycleEvent::class);

    expect($reflection->isAbstract())->toBeTrue()
        ->and($reflection->hasProperty('entity'))->toBeTrue()
        ->and($reflection->hasProperty('entityClass'))->toBeTrue();

    $entity = $reflection->getProperty('entity');
    $entityClass = $reflection->getProperty('entityClass');

    expect($entity->isPublic())->toBeTrue()
        ->and($entity->isReadOnly())->toBeTrue()
        ->and($entityClass->isPublic())->toBeTrue()
        ->and($entityClass->isReadOnly())->toBeTrue();
});

it('extends the base Event class via EntityLifecycleEvent for all lifecycle events', function (): void {
    $reflection = new ReflectionClass(EntityLifecycleEvent::class);
    expect($reflection->getParentClass()->getName())->toBe(Event::class);
});

it('extends EntityLifecycleEvent for all 6 concrete lifecycle event classes', function (): void {
    $concreteClasses = [
        EntityCreating::class,
        EntityCreated::class,
        EntityUpdating::class,
        EntityUpdated::class,
        EntityDeleting::class,
        EntityDeleted::class,
    ];

    foreach ($concreteClasses as $class) {
        $reflection = new ReflectionClass($class);
        expect($reflection->getParentClass()->getName())
            ->toBe(EntityLifecycleEvent::class, "$class should extend EntityLifecycleEvent");
    }
});

it('creates EntityCreating event with entity and entity class', function (): void {
    $item = new LifecycleItem();
    $item->name = 'Test';

    $event = new EntityCreating($item, LifecycleItem::class);

    expect($event->entity)->toBe($item)
        ->and($event->entityClass)->toBe(LifecycleItem::class);
});

it('creates EntityCreated event with entity and entity class', function (): void {
    $item = new LifecycleItem();
    $item->name = 'Test';

    $event = new EntityCreated($item, LifecycleItem::class);

    expect($event->entity)->toBe($item)
        ->and($event->entityClass)->toBe(LifecycleItem::class);
});

it('creates EntityUpdating event with entity and entity class', function (): void {
    $item = new LifecycleItem();
    $item->name = 'Test';

    $event = new EntityUpdating($item, LifecycleItem::class);

    expect($event->entity)->toBe($item)
        ->and($event->entityClass)->toBe(LifecycleItem::class);
});

it('creates EntityUpdated event with entity and entity class', function (): void {
    $item = new LifecycleItem();
    $item->name = 'Test';

    $event = new EntityUpdated($item, LifecycleItem::class);

    expect($event->entity)->toBe($item)
        ->and($event->entityClass)->toBe(LifecycleItem::class);
});

it('creates EntityDeleting event with entity and entity class', function (): void {
    $item = new LifecycleItem();
    $item->name = 'Test';

    $event = new EntityDeleting($item, LifecycleItem::class);

    expect($event->entity)->toBe($item)
        ->and($event->entityClass)->toBe(LifecycleItem::class);
});

it('creates EntityDeleted event with entity and entity class', function (): void {
    $item = new LifecycleItem();
    $item->name = 'Test';

    $event = new EntityDeleted($item, LifecycleItem::class);

    expect($event->entity)->toBe($item)
        ->and($event->entityClass)->toBe(LifecycleItem::class);
});
