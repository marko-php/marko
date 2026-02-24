<?php

declare(strict_types=1);

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Marko\Notification\Database\Entity\DatabaseNotification;

test('it defines DatabaseNotification entity with Table and Column attributes', function (): void {
    $reflection = new ReflectionClass(DatabaseNotification::class);

    expect($reflection->isSubclassOf(Entity::class))->toBeTrue();

    $tableAttributes = $reflection->getAttributes(Table::class);
    expect($tableAttributes)->toHaveCount(1);

    $table = $tableAttributes[0]->newInstance();
    expect($table->name)->toBe('notifications');
});

test('it has id, type, notifiableType, notifiableId, data, readAt, and createdAt properties', function (): void {
    $reflection = new ReflectionClass(DatabaseNotification::class);

    expect($reflection->hasProperty('id'))->toBeTrue()
        ->and($reflection->hasProperty('type'))->toBeTrue()
        ->and($reflection->hasProperty('notifiableType'))->toBeTrue()
        ->and($reflection->hasProperty('notifiableId'))->toBeTrue()
        ->and($reflection->hasProperty('data'))->toBeTrue()
        ->and($reflection->hasProperty('readAt'))->toBeTrue()
        ->and($reflection->hasProperty('createdAt'))->toBeTrue();
});

test('it maps entity properties to correct column types', function (): void {
    $reflection = new ReflectionClass(DatabaseNotification::class);

    // id: varchar(36) primary key
    $idColumn = $reflection->getProperty('id')->getAttributes(Column::class)[0]->newInstance();
    expect($idColumn->type)->toBe('varchar')
        ->and($idColumn->length)->toBe(36)
        ->and($idColumn->primaryKey)->toBeTrue();

    // type: varchar(255)
    $typeColumn = $reflection->getProperty('type')->getAttributes(Column::class)[0]->newInstance();
    expect($typeColumn->type)->toBe('varchar')
        ->and($typeColumn->length)->toBe(255);

    // notifiableType: varchar(255)
    $ntColumn = $reflection->getProperty('notifiableType')->getAttributes(Column::class)[0]->newInstance();
    expect($ntColumn->type)->toBe('varchar')
        ->and($ntColumn->length)->toBe(255);

    // notifiableId: varchar(255)
    $niColumn = $reflection->getProperty('notifiableId')->getAttributes(Column::class)[0]->newInstance();
    expect($niColumn->type)->toBe('varchar')
        ->and($niColumn->length)->toBe(255);

    // data: text
    $dataColumn = $reflection->getProperty('data')->getAttributes(Column::class)[0]->newInstance();
    expect($dataColumn->type)->toBe('text');

    // readAt: timestamp (nullable via PHP type)
    $readAtColumn = $reflection->getProperty('readAt')->getAttributes(Column::class)[0]->newInstance();
    expect($readAtColumn->type)->toBe('timestamp');
    $readAtProp = $reflection->getProperty('readAt');
    expect($readAtProp->getType()?->allowsNull())->toBeTrue();

    // createdAt: timestamp
    $createdAtColumn = $reflection->getProperty('createdAt')->getAttributes(Column::class)[0]->newInstance();
    expect($createdAtColumn->type)->toBe('timestamp');
});
