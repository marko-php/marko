<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Entity;

use Marko\Database\Entity\RelationshipMetadata;
use Marko\Database\Entity\RelationshipType;

describe('RelationshipType', function (): void {
    it('defines HasOne relationship type', function (): void {
        expect(RelationshipType::HasOne->value)->toBe('has_one');
    });

    it('defines HasMany relationship type', function (): void {
        expect(RelationshipType::HasMany->value)->toBe('has_many');
    });

    it('defines BelongsTo relationship type', function (): void {
        expect(RelationshipType::BelongsTo->value)->toBe('belongs_to');
    });

    it('defines BelongsToMany relationship type', function (): void {
        expect(RelationshipType::BelongsToMany->value)->toBe('belongs_to_many');
    });
});

describe('RelationshipMetadata', function (): void {
    it('creates metadata for a HasOne relationship', function (): void {
        $metadata = new RelationshipMetadata(
            propertyName: 'profile',
            type: RelationshipType::HasOne,
            relatedClass: 'App\Entity\Profile',
            foreignKey: 'user_id',
        );

        expect($metadata->type)->toBe(RelationshipType::HasOne);
    });

    it('creates metadata for a HasMany relationship', function (): void {
        $metadata = new RelationshipMetadata(
            propertyName: 'posts',
            type: RelationshipType::HasMany,
            relatedClass: 'App\Entity\Post',
            foreignKey: 'user_id',
        );

        expect($metadata->type)->toBe(RelationshipType::HasMany);
    });

    it('creates metadata for a BelongsTo relationship', function (): void {
        $metadata = new RelationshipMetadata(
            propertyName: 'user',
            type: RelationshipType::BelongsTo,
            relatedClass: 'App\Entity\User',
            foreignKey: 'user_id',
        );

        expect($metadata->type)->toBe(RelationshipType::BelongsTo);
    });

    it('creates metadata for a BelongsToMany relationship with pivot class', function (): void {
        $metadata = new RelationshipMetadata(
            propertyName: 'tags',
            type: RelationshipType::BelongsToMany,
            relatedClass: 'App\Entity\Tag',
            foreignKey: 'post_id',
            relatedKey: 'tag_id',
            pivotClass: 'App\Entity\PostTag',
        );

        expect($metadata->type)->toBe(RelationshipType::BelongsToMany)
            ->and($metadata->pivotClass)->toBe('App\Entity\PostTag');
    });

    it('stores the property name the relationship is defined on', function (): void {
        $metadata = new RelationshipMetadata(
            propertyName: 'profile',
            type: RelationshipType::HasOne,
            relatedClass: 'App\Entity\Profile',
            foreignKey: 'user_id',
        );

        expect($metadata->propertyName)->toBe('profile');
    });

    it('stores the related entity class', function (): void {
        $metadata = new RelationshipMetadata(
            propertyName: 'profile',
            type: RelationshipType::HasOne,
            relatedClass: 'App\Entity\Profile',
            foreignKey: 'user_id',
        );

        expect($metadata->relatedClass)->toBe('App\Entity\Profile');
    });

    it('stores the foreign key column name', function (): void {
        $metadata = new RelationshipMetadata(
            propertyName: 'profile',
            type: RelationshipType::HasOne,
            relatedClass: 'App\Entity\Profile',
            foreignKey: 'user_id',
        );

        expect($metadata->foreignKey)->toBe('user_id');
    });

    it('stores the related key for BelongsToMany', function (): void {
        $metadata = new RelationshipMetadata(
            propertyName: 'tags',
            type: RelationshipType::BelongsToMany,
            relatedClass: 'App\Entity\Tag',
            foreignKey: 'post_id',
            relatedKey: 'tag_id',
        );

        expect($metadata->relatedKey)->toBe('tag_id');
    });

    it('stores the pivot class for BelongsToMany', function (): void {
        $metadata = new RelationshipMetadata(
            propertyName: 'tags',
            type: RelationshipType::BelongsToMany,
            relatedClass: 'App\Entity\Tag',
            foreignKey: 'post_id',
            pivotClass: 'App\Entity\PostTag',
        );

        expect($metadata->pivotClass)->toBe('App\Entity\PostTag');
    });

    it('returns null for pivot class on non-BelongsToMany relationships', function (): void {
        $metadata = new RelationshipMetadata(
            propertyName: 'profile',
            type: RelationshipType::HasOne,
            relatedClass: 'App\Entity\Profile',
            foreignKey: 'user_id',
        );

        expect($metadata->pivotClass)->toBeNull();
    });

    it('returns null for related key on non-BelongsToMany relationships', function (): void {
        $metadata = new RelationshipMetadata(
            propertyName: 'profile',
            type: RelationshipType::HasOne,
            relatedClass: 'App\Entity\Profile',
            foreignKey: 'user_id',
        );

        expect($metadata->relatedKey)->toBeNull();
    });

    it('identifies single-result relationships via isSingular', function (): void {
        $hasOne = new RelationshipMetadata(
            propertyName: 'profile',
            type: RelationshipType::HasOne,
            relatedClass: 'App\Entity\Profile',
            foreignKey: 'user_id',
        );

        $belongsTo = new RelationshipMetadata(
            propertyName: 'user',
            type: RelationshipType::BelongsTo,
            relatedClass: 'App\Entity\User',
            foreignKey: 'user_id',
        );

        $hasMany = new RelationshipMetadata(
            propertyName: 'posts',
            type: RelationshipType::HasMany,
            relatedClass: 'App\Entity\Post',
            foreignKey: 'user_id',
        );

        expect($hasOne->isSingular())->toBeTrue()
            ->and($belongsTo->isSingular())->toBeTrue()
            ->and($hasMany->isSingular())->toBeFalse();
    });

    it('identifies collection relationships via isCollection', function (): void {
        $hasMany = new RelationshipMetadata(
            propertyName: 'posts',
            type: RelationshipType::HasMany,
            relatedClass: 'App\Entity\Post',
            foreignKey: 'user_id',
        );

        $belongsToMany = new RelationshipMetadata(
            propertyName: 'tags',
            type: RelationshipType::BelongsToMany,
            relatedClass: 'App\Entity\Tag',
            foreignKey: 'post_id',
        );

        $hasOne = new RelationshipMetadata(
            propertyName: 'profile',
            type: RelationshipType::HasOne,
            relatedClass: 'App\Entity\Profile',
            foreignKey: 'user_id',
        );

        expect($hasMany->isCollection())->toBeTrue()
            ->and($belongsToMany->isCollection())->toBeTrue()
            ->and($hasOne->isCollection())->toBeFalse();
    });
});
