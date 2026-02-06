<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Tests\Unit\Entity;

use Marko\AdminAuth\Entity\AdminUser;
use Marko\AdminAuth\Entity\AdminUserInterface;
use Marko\AdminAuth\Entity\Role;
use Marko\Auth\AuthenticatableInterface;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use ReflectionClass;

it('creates AdminUser entity implementing AuthenticatableInterface', function (): void {
    $user = new AdminUser();

    expect($user)->toBeInstanceOf(Entity::class)
        ->and($user)->toBeInstanceOf(AuthenticatableInterface::class)
        ->and($user)->toBeInstanceOf(AdminUserInterface::class);

    $reflection = new ReflectionClass(AdminUser::class);
    $tableAttributes = $reflection->getAttributes(Table::class);

    expect($tableAttributes)->toHaveCount(1);

    $tableAttribute = $tableAttributes[0]->newInstance();
    expect($tableAttribute->name)->toBe('admin_users');
});

it('has email, password, name, rememberToken, isActive properties', function (): void {
    $user = new AdminUser();
    $user->id = 1;
    $user->email = 'admin@example.com';
    $user->password = 'hashed_password';
    $user->name = 'Admin User';
    $user->rememberToken = 'token123';
    $user->isActive = '1';

    expect($user->id)->toBe(1)
        ->and($user->email)->toBe('admin@example.com')
        ->and($user->password)->toBe('hashed_password')
        ->and($user->name)->toBe('Admin User')
        ->and($user->rememberToken)->toBe('token123')
        ->and($user->isActive)->toBe('1');

    $reflection = new ReflectionClass(AdminUser::class);

    $idProp = $reflection->getProperty('id');
    $idColumn = $idProp->getAttributes(Column::class)[0]->newInstance();
    expect($idColumn->primaryKey)->toBeTrue()
        ->and($idColumn->autoIncrement)->toBeTrue();

    $emailProp = $reflection->getProperty('email');
    $emailColumn = $emailProp->getAttributes(Column::class)[0]->newInstance();
    expect($emailColumn->unique)->toBeTrue();

    $passwordProp = $reflection->getProperty('password');
    expect($passwordProp->getAttributes(Column::class))->toHaveCount(1);

    $nameProp = $reflection->getProperty('name');
    expect($nameProp->getAttributes(Column::class))->toHaveCount(1);

    $rememberTokenProp = $reflection->getProperty('rememberToken');
    $rememberTokenColumn = $rememberTokenProp->getAttributes(Column::class)[0]->newInstance();
    expect($rememberTokenColumn->name)->toBe('remember_token')
        ->and($rememberTokenProp->getType()->allowsNull())->toBeTrue();

    $isActiveProp = $reflection->getProperty('isActive');
    $isActiveColumn = $isActiveProp->getAttributes(Column::class)[0]->newInstance();
    expect($isActiveColumn->name)->toBe('is_active')
        ->and($isActiveColumn->default)->toBe('1');
});

it('returns auth identifier as id', function (): void {
    $user = new AdminUser();
    $user->id = 42;
    $user->email = 'admin@example.com';
    $user->password = 'hashed';
    $user->name = 'Admin';

    expect($user->getAuthIdentifier())->toBe(42);
});

it('returns auth identifier name as id', function (): void {
    $user = new AdminUser();

    expect($user->getAuthIdentifierName())->toBe('id');
});

it('returns auth password from password property', function (): void {
    $user = new AdminUser();
    $user->email = 'admin@example.com';
    $user->password = '$2y$10$hashedpassword';
    $user->name = 'Admin';

    expect($user->getAuthPassword())->toBe('$2y$10$hashedpassword');
});

it('supports remember token get and set', function (): void {
    $user = new AdminUser();
    $user->email = 'admin@example.com';
    $user->password = 'hashed';
    $user->name = 'Admin';

    expect($user->getRememberToken())->toBeNull()
        ->and($user->getRememberTokenName())->toBe('remember_token');

    $user->setRememberToken('abc123');
    expect($user->getRememberToken())->toBe('abc123');

    $user->setRememberToken(null);
    expect($user->getRememberToken())->toBeNull();
});

it('checks hasPermission against loaded roles', function (): void {
    $user = new AdminUser();
    $user->email = 'admin@example.com';
    $user->password = 'hashed';
    $user->name = 'Admin';

    $editorRole = new Role();
    $editorRole->id = 1;
    $editorRole->name = 'Editor';
    $editorRole->slug = 'editor';

    $user->setRoles(
        roles: [$editorRole],
        permissionKeys: ['posts.create', 'posts.edit'],
    );

    expect($user->hasPermission('posts.create'))->toBeTrue()
        ->and($user->hasPermission('posts.edit'))->toBeTrue()
        ->and($user->hasPermission('posts.delete'))->toBeFalse();
});

it('returns true for any permission when user has super admin role', function (): void {
    $user = new AdminUser();
    $user->email = 'superadmin@example.com';
    $user->password = 'hashed';
    $user->name = 'Super Admin';

    $superAdminRole = new Role();
    $superAdminRole->id = 1;
    $superAdminRole->name = 'Super Admin';
    $superAdminRole->slug = 'super-admin';
    $superAdminRole->isSuperAdmin = '1';

    $user->setRoles(
        roles: [$superAdminRole],
        permissionKeys: [],
    );

    expect($user->hasPermission('posts.create'))->toBeTrue()
        ->and($user->hasPermission('posts.delete'))->toBeTrue()
        ->and($user->hasPermission('users.manage'))->toBeTrue()
        ->and($user->hasPermission('anything.at.all'))->toBeTrue();
});

it('checks hasRole by role slug', function (): void {
    $user = new AdminUser();
    $user->email = 'admin@example.com';
    $user->password = 'hashed';
    $user->name = 'Admin';

    $editorRole = new Role();
    $editorRole->id = 1;
    $editorRole->name = 'Editor';
    $editorRole->slug = 'editor';

    $moderatorRole = new Role();
    $moderatorRole->id = 2;
    $moderatorRole->name = 'Moderator';
    $moderatorRole->slug = 'moderator';

    $user->setRoles(
        roles: [$editorRole, $moderatorRole],
    );

    expect($user->hasRole('editor'))->toBeTrue()
        ->and($user->hasRole('moderator'))->toBeTrue()
        ->and($user->hasRole('admin'))->toBeFalse();
});

it('returns false for hasPermission when no roles loaded', function (): void {
    $user = new AdminUser();
    $user->email = 'admin@example.com';
    $user->password = 'hashed';
    $user->name = 'Admin';

    expect($user->hasPermission('posts.create'))->toBeFalse()
        ->and($user->hasPermission('anything'))->toBeFalse()
        ->and($user->getRoles())->toBeEmpty();
});
