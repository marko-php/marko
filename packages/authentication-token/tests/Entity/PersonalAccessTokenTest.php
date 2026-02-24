<?php

declare(strict_types=1);

namespace Marko\AuthenticationToken\Tests\Entity;

use Marko\AuthenticationToken\Entity\PersonalAccessToken;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use ReflectionClass;

it('defines PersonalAccessToken entity with table and column attributes', function (): void {
    $reflection = new ReflectionClass(PersonalAccessToken::class);

    // Extends Entity base class
    expect($reflection->getParentClass()->getName())->toBe(Entity::class);

    // Has Table attribute with correct table name
    $tableAttributes = $reflection->getAttributes(Table::class);
    $tableAttribute = $tableAttributes[0]->newInstance();
    expect($tableAttributes)->toHaveCount(1)
        ->and($tableAttribute->name)->toBe('personal_access_tokens');

    // Has id column with primaryKey and autoIncrement
    $idProperty = $reflection->getProperty('id');
    $idColumns = $idProperty->getAttributes(Column::class);
    $idColumn = $idColumns[0]->newInstance();
    expect($idColumns)->toHaveCount(1)
        ->and($idColumn->primaryKey)->toBeTrue()
        ->and($idColumn->autoIncrement)->toBeTrue();

    // Has tokenable_type column
    $tokenableTypeProperty = $reflection->getProperty('tokenableType');
    $tokenableTypeColumns = $tokenableTypeProperty->getAttributes(Column::class);
    $tokenableTypeColumn = $tokenableTypeColumns[0]->newInstance();
    expect($tokenableTypeColumns)->toHaveCount(1)
        ->and($tokenableTypeColumn->name)->toBe('tokenable_type');

    // Has tokenable_id column
    $tokenableIdProperty = $reflection->getProperty('tokenableId');
    $tokenableIdColumns = $tokenableIdProperty->getAttributes(Column::class);
    $tokenableIdColumn = $tokenableIdColumns[0]->newInstance();
    expect($tokenableIdColumns)->toHaveCount(1)
        ->and($tokenableIdColumn->name)->toBe('tokenable_id');

    // Has name column
    $nameProperty = $reflection->getProperty('name');
    $nameColumns = $nameProperty->getAttributes(Column::class);
    expect($nameColumns)->toHaveCount(1);

    // Has token_hash column with length 64
    $tokenHashProperty = $reflection->getProperty('tokenHash');
    $tokenHashColumns = $tokenHashProperty->getAttributes(Column::class);
    $tokenHashColumn = $tokenHashColumns[0]->newInstance();
    expect($tokenHashColumns)->toHaveCount(1)
        ->and($tokenHashColumn->name)->toBe('token_hash')
        ->and($tokenHashColumn->length)->toBe(64);

    // Has abilities column (text, nullable)
    $abilitiesProperty = $reflection->getProperty('abilities');
    $abilitiesColumns = $abilitiesProperty->getAttributes(Column::class);
    $abilitiesColumn = $abilitiesColumns[0]->newInstance();
    expect($abilitiesColumns)->toHaveCount(1)
        ->and($abilitiesColumn->type)->toBe('text');

    // Has last_used_at column (nullable)
    $lastUsedAtProperty = $reflection->getProperty('lastUsedAt');
    $lastUsedAtColumns = $lastUsedAtProperty->getAttributes(Column::class);
    $lastUsedAtColumn = $lastUsedAtColumns[0]->newInstance();
    expect($lastUsedAtColumns)->toHaveCount(1)
        ->and($lastUsedAtColumn->name)->toBe('last_used_at');

    // Has expires_at column (nullable)
    $expiresAtProperty = $reflection->getProperty('expiresAt');
    $expiresAtColumns = $expiresAtProperty->getAttributes(Column::class);
    $expiresAtColumn = $expiresAtColumns[0]->newInstance();
    expect($expiresAtColumns)->toHaveCount(1)
        ->and($expiresAtColumn->name)->toBe('expires_at');

    // Has created_at column (nullable)
    $createdAtProperty = $reflection->getProperty('createdAt');
    $createdAtColumns = $createdAtProperty->getAttributes(Column::class);
    $createdAtColumn = $createdAtColumns[0]->newInstance();
    expect($createdAtColumns)->toHaveCount(1)
        ->and($createdAtColumn->name)->toBe('created_at');
});
