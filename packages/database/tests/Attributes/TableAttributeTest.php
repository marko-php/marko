<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Attributes;

use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use ReflectionClass;

#[Table('users')]
class UserEntity extends Entity {}

it('creates #[Table] attribute with table name parameter', function (): void {
    $reflection = new ReflectionClass(UserEntity::class);
    $attributes = $reflection->getAttributes(Table::class);
    $tableAttribute = $attributes[0]->newInstance();

    expect($attributes)->toHaveCount(1)
        ->and($tableAttribute)->toBeInstanceOf(Table::class)
        ->and($tableAttribute->name)->toBe('users');
});

describe('Table', function (): void {
    it('constructs with name only (no extends)', function (): void {
        $table = new Table(name: 'users');

        expect($table->name)->toBe('users')
            ->and($table->extends)->toBeNull();
    });

    it('constructs with extends only (no name)', function (): void {
        $table = new Table(extends: 'App\Entity\BaseUser');

        expect($table->name)->toBeNull()
            ->and($table->extends)->toBe('App\Entity\BaseUser');
    });

    it('constructs with both name and extends set', function (): void {
        $table = new Table(name: 'users', extends: 'App\Entity\BaseUser');

        expect($table->name)->toBe('users')
            ->and($table->extends)->toBe('App\Entity\BaseUser');
    });

    it('constructs with neither name nor extends (validation deferred to factory)', function (): void {
        $table = new Table();

        expect($table->name)->toBeNull()
            ->and($table->extends)->toBeNull();
    });

    it('exposes extends as nullable class-string property', function (): void {
        $withExtends = new Table(extends: 'App\Entity\BaseUser');
        $withoutExtends = new Table(name: 'users');

        expect($withExtends->extends)->toBe('App\Entity\BaseUser')
            ->and($withoutExtends->extends)->toBeNull();
    });

    it('exposes name as nullable string property', function (): void {
        $withName = new Table(name: 'orders');
        $withoutName = new Table(extends: 'App\Entity\BaseOrder');

        expect($withName->name)->toBe('orders')
            ->and($withoutName->name)->toBeNull();
    });
});
