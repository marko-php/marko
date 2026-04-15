<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Attributes;

use Attribute;
use Marko\Database\Attributes\BelongsTo;
use Marko\Database\Attributes\BelongsToMany;
use Marko\Database\Attributes\HasMany;
use Marko\Database\Attributes\HasOne;
use Marko\Database\Entity\Entity;
use ReflectionClass;
use ReflectionProperty;

class RelUserEntity extends Entity
{
    #[HasOne(RelProfileEntity::class, foreignKey: 'user_id')]
    public ?RelProfileEntity $profile = null;

    #[HasMany(RelPostEntity::class, foreignKey: 'author_id')]
    public array $posts = [];

    #[BelongsToMany(RelRoleEntity::class, pivotClass: RelUserRoleEntity::class, foreignKey: 'user_id', relatedKey: 'role_id')]
    public array $roles = [];
}

class RelProfileEntity extends Entity
{
    #[BelongsTo(RelUserEntity::class, foreignKey: 'user_id')]
    public ?RelUserEntity $user = null;
}

class RelPostEntity extends Entity {}

class RelRoleEntity extends Entity {}

class RelUserRoleEntity extends Entity {}

describe('HasOne Attribute', function (): void {
    it('creates HasOne attribute with entity class and foreign key', function (): void {
        $property = new ReflectionProperty(RelUserEntity::class, 'profile');
        $attributes = $property->getAttributes(HasOne::class);

        expect($attributes)->toHaveCount(1)
            ->and($attributes[0]->newInstance())->toBeInstanceOf(HasOne::class);
    });

    it('stores entity class on HasOne attribute', function (): void {
        $property = new ReflectionProperty(RelUserEntity::class, 'profile');
        $hasOne = $property->getAttributes(HasOne::class)[0]->newInstance();

        expect($hasOne->entityClass)->toBe(RelProfileEntity::class);
    });

    it('stores foreign key on HasOne attribute', function (): void {
        $property = new ReflectionProperty(RelUserEntity::class, 'profile');
        $hasOne = $property->getAttributes(HasOne::class)[0]->newInstance();

        expect($hasOne->foreignKey)->toBe('user_id');
    });

    it('targets properties only for HasOne', function (): void {
        $reflection = new ReflectionClass(HasOne::class);
        $attrAttributes = $reflection->getAttributes(Attribute::class);
        $flags = $attrAttributes[0]->newInstance()->flags;

        expect($flags)->toBe(Attribute::TARGET_PROPERTY);
    });
});

describe('HasMany Attribute', function (): void {
    it('creates HasMany attribute with entity class and foreign key', function (): void {
        $property = new ReflectionProperty(RelUserEntity::class, 'posts');
        $attributes = $property->getAttributes(HasMany::class);

        expect($attributes)->toHaveCount(1)
            ->and($attributes[0]->newInstance())->toBeInstanceOf(HasMany::class);
    });

    it('stores entity class on HasMany attribute', function (): void {
        $property = new ReflectionProperty(RelUserEntity::class, 'posts');
        $hasMany = $property->getAttributes(HasMany::class)[0]->newInstance();

        expect($hasMany->entityClass)->toBe(RelPostEntity::class);
    });

    it('stores foreign key on HasMany attribute', function (): void {
        $property = new ReflectionProperty(RelUserEntity::class, 'posts');
        $hasMany = $property->getAttributes(HasMany::class)[0]->newInstance();

        expect($hasMany->foreignKey)->toBe('author_id');
    });

    it('targets properties only for HasMany', function (): void {
        $reflection = new ReflectionClass(HasMany::class);
        $attrAttributes = $reflection->getAttributes(Attribute::class);
        $flags = $attrAttributes[0]->newInstance()->flags;

        expect($flags)->toBe(Attribute::TARGET_PROPERTY);
    });
});

describe('BelongsTo Attribute', function (): void {
    it('creates BelongsTo attribute with entity class and foreign key', function (): void {
        $property = new ReflectionProperty(RelProfileEntity::class, 'user');
        $attributes = $property->getAttributes(BelongsTo::class);

        expect($attributes)->toHaveCount(1)
            ->and($attributes[0]->newInstance())->toBeInstanceOf(BelongsTo::class);
    });

    it('stores entity class on BelongsTo attribute', function (): void {
        $property = new ReflectionProperty(RelProfileEntity::class, 'user');
        $belongsTo = $property->getAttributes(BelongsTo::class)[0]->newInstance();

        expect($belongsTo->entityClass)->toBe(RelUserEntity::class);
    });

    it('stores foreign key on BelongsTo attribute', function (): void {
        $property = new ReflectionProperty(RelProfileEntity::class, 'user');
        $belongsTo = $property->getAttributes(BelongsTo::class)[0]->newInstance();

        expect($belongsTo->foreignKey)->toBe('user_id');
    });

    it('targets properties only for BelongsTo', function (): void {
        $reflection = new ReflectionClass(BelongsTo::class);
        $attrAttributes = $reflection->getAttributes(Attribute::class);
        $flags = $attrAttributes[0]->newInstance()->flags;

        expect($flags)->toBe(Attribute::TARGET_PROPERTY);
    });
});

describe('BelongsToMany Attribute', function (): void {
    it('creates BelongsToMany attribute with entity class pivot class and keys', function (): void {
        $property = new ReflectionProperty(RelUserEntity::class, 'roles');
        $attributes = $property->getAttributes(BelongsToMany::class);

        expect($attributes)->toHaveCount(1)
            ->and($attributes[0]->newInstance())->toBeInstanceOf(BelongsToMany::class);
    });

    it('stores entity class on BelongsToMany attribute', function (): void {
        $property = new ReflectionProperty(RelUserEntity::class, 'roles');
        $belongsToMany = $property->getAttributes(BelongsToMany::class)[0]->newInstance();

        expect($belongsToMany->entityClass)->toBe(RelRoleEntity::class);
    });

    it('stores pivot class on BelongsToMany attribute', function (): void {
        $property = new ReflectionProperty(RelUserEntity::class, 'roles');
        $belongsToMany = $property->getAttributes(BelongsToMany::class)[0]->newInstance();

        expect($belongsToMany->pivotClass)->toBe(RelUserRoleEntity::class);
    });

    it('stores foreign key on BelongsToMany attribute', function (): void {
        $property = new ReflectionProperty(RelUserEntity::class, 'roles');
        $belongsToMany = $property->getAttributes(BelongsToMany::class)[0]->newInstance();

        expect($belongsToMany->foreignKey)->toBe('user_id');
    });

    it('stores related key on BelongsToMany attribute', function (): void {
        $property = new ReflectionProperty(RelUserEntity::class, 'roles');
        $belongsToMany = $property->getAttributes(BelongsToMany::class)[0]->newInstance();

        expect($belongsToMany->relatedKey)->toBe('role_id');
    });

    it('targets properties only for BelongsToMany', function (): void {
        $reflection = new ReflectionClass(BelongsToMany::class);
        $attrAttributes = $reflection->getAttributes(Attribute::class);
        $flags = $attrAttributes[0]->newInstance()->flags;

        expect($flags)->toBe(Attribute::TARGET_PROPERTY);
    });
});
