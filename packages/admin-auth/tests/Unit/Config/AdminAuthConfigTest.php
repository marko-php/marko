<?php

declare(strict_types=1);

use Marko\AdminAuth\AdminUserProvider;
use Marko\AdminAuth\Config\AdminAuthConfig;
use Marko\AdminAuth\Config\AdminAuthConfigInterface;
use Marko\AdminAuth\Entity\AdminUser;
use Marko\AdminAuth\Entity\AdminUserInterface;
use Marko\AdminAuth\Entity\Role;
use Marko\AdminAuth\Entity\RoleInterface;
use Marko\AdminAuth\Events\AdminUserCreated;
use Marko\AdminAuth\Events\AdminUserDeleted;
use Marko\AdminAuth\Events\AdminUserUpdated;
use Marko\AdminAuth\Events\PermissionsSynced;
use Marko\AdminAuth\Events\RoleCreated;
use Marko\AdminAuth\Events\RoleDeleted;
use Marko\AdminAuth\Events\RoleUpdated;
use Marko\AdminAuth\Repository\AdminUserRepository;
use Marko\AdminAuth\Repository\AdminUserRepositoryInterface;
use Marko\AdminAuth\Repository\PermissionRepository;
use Marko\AdminAuth\Repository\PermissionRepositoryInterface;
use Marko\AdminAuth\Repository\RoleRepository;
use Marko\AdminAuth\Repository\RoleRepositoryInterface;
use Marko\Authentication\Contracts\PasswordHasherInterface;
use Marko\Authentication\Contracts\UserProviderInterface;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Event\Event;
use Marko\Testing\Fake\FakeConfigRepository;

it('creates AdminAuthConfig with guard name and super admin role slug', function (): void {
    $config = new AdminAuthConfig(new FakeConfigRepository([
        'admin-auth.guard' => 'admin',
        'admin-auth.super_admin_role' => 'super-admin',
    ]));

    expect($config)->toBeInstanceOf(AdminAuthConfigInterface::class)
        ->and($config->getGuardName())->toBe('admin')
        ->and($config->getSuperAdminRoleSlug())->toBe('super-admin');
});

it('binds AdminUserRepositoryInterface to AdminUserRepository in module.php', function (): void {
    $modulePath = dirname(__DIR__, 3) . '/module.php';
    $module = require $modulePath;

    expect(file_exists($modulePath))->toBeTrue()
        ->and($module)->toBeArray()
        ->and($module)->toHaveKey('bindings')
        ->and($module['bindings'])->toHaveKey(AdminUserRepositoryInterface::class)
        ->and($module['bindings'][AdminUserRepositoryInterface::class])
            ->toBe(AdminUserRepository::class);
});

it('binds RoleRepositoryInterface to RoleRepository in module.php', function (): void {
    $modulePath = dirname(__DIR__, 3) . '/module.php';

    $module = require $modulePath;

    expect($module['bindings'])->toHaveKey(RoleRepositoryInterface::class)
        ->and($module['bindings'][RoleRepositoryInterface::class])
            ->toBe(RoleRepository::class);
});

it('binds PermissionRepositoryInterface to PermissionRepository in module.php', function (): void {
    $modulePath = dirname(__DIR__, 3) . '/module.php';

    $module = require $modulePath;

    expect($module['bindings'])->toHaveKey(PermissionRepositoryInterface::class)
        ->and($module['bindings'][PermissionRepositoryInterface::class])
            ->toBe(PermissionRepository::class);
});

it('binds AdminUserProvider as factory with password hasher dependency in module.php', function (): void {
    $modulePath = dirname(__DIR__, 3) . '/module.php';

    $module = require $modulePath;

    expect($module['bindings'])->toHaveKey(UserProviderInterface::class)
        ->and($module['bindings'][UserProviderInterface::class])->toBeInstanceOf(Closure::class);

    $userRepository = $this->createMock(AdminUserRepositoryInterface::class);
    $roleRepository = $this->createMock(RoleRepositoryInterface::class);
    $passwordHasher = $this->createMock(PasswordHasherInterface::class);

    $container = $this->createMock(ContainerInterface::class);
    $container->expects($this->exactly(3))
        ->method('get')
        ->willReturnCallback(function (string $id) use ($userRepository, $roleRepository, $passwordHasher) {
            return match ($id) {
                AdminUserRepositoryInterface::class => $userRepository,
                RoleRepositoryInterface::class => $roleRepository,
                PasswordHasherInterface::class => $passwordHasher,
            };
        });

    $binding = $module['bindings'][UserProviderInterface::class];
    $result = $binding($container);

    expect($result)->toBeInstanceOf(AdminUserProvider::class)
        ->and($result)->toBeInstanceOf(UserProviderInterface::class);
});

it('creates RoleCreated, RoleUpdated, RoleDeleted events', function (): void {
    $role = new Role();
    $role->id = 1;
    $role->name = 'Editor';
    $role->slug = 'editor';

    $created = new RoleCreated(role: $role);
    $updated = new RoleUpdated(role: $role);
    $deleted = new RoleDeleted(role: $role);

    expect($created)->toBeInstanceOf(Event::class)
        ->and($created->getRole())->toBeInstanceOf(RoleInterface::class)
        ->and($created->getRole()->getName())->toBe('Editor')
        ->and($created->getTimestamp())->toBeInstanceOf(DateTimeImmutable::class)
        ->and($updated)->toBeInstanceOf(Event::class)
        ->and($updated->getRole()->getSlug())->toBe('editor')
        ->and($deleted)->toBeInstanceOf(Event::class)
        ->and($deleted->getRole()->getId())->toBe(1);
});

it('creates AdminUserCreated, AdminUserUpdated, AdminUserDeleted events', function (): void {
    $user = new AdminUser();
    $user->id = 1;
    $user->email = 'admin@example.com';
    $user->password = 'hashed';
    $user->name = 'Admin';

    $created = new AdminUserCreated(user: $user);
    $updated = new AdminUserUpdated(user: $user);
    $deleted = new AdminUserDeleted(user: $user);

    expect($created)->toBeInstanceOf(Event::class)
        ->and($created->getUser())->toBeInstanceOf(AdminUserInterface::class)
        ->and($created->getUser()->getAuthIdentifier())->toBe(1)
        ->and($created->getTimestamp())->toBeInstanceOf(DateTimeImmutable::class)
        ->and($updated)->toBeInstanceOf(Event::class)
        ->and($updated->getUser()->getAuthIdentifier())->toBe(1)
        ->and($deleted)->toBeInstanceOf(Event::class)
        ->and($deleted->getUser())->toBeInstanceOf(AdminUserInterface::class)
        ->and($deleted->getTimestamp())->toBeInstanceOf(DateTimeImmutable::class);
});

it('creates PermissionsSynced event dispatched after registry sync', function (): void {
    $event = new PermissionsSynced(
        createdCount: 5,
        totalCount: 12,
    );

    expect($event)->toBeInstanceOf(Event::class)
        ->and($event->getCreatedCount())->toBe(5)
        ->and($event->getTotalCount())->toBe(12)
        ->and($event->getTimestamp())->toBeInstanceOf(DateTimeImmutable::class);
});

it('has valid config/admin-auth.php with default values', function (): void {
    $configPath = dirname(__DIR__, 3) . '/config/admin-auth.php';
    $configData = require $configPath;

    expect(file_exists($configPath))->toBeTrue()
        ->and($configData)->toBeArray()
        ->and($configData)->toHaveKey('guard')
        ->and($configData)->toHaveKey('super_admin_role')
        ->and($configData['guard'])->toBe('admin')
        ->and($configData['super_admin_role'])->toBe('super-admin');
});

it('has module.php with all required bindings', function (): void {
    $modulePath = dirname(__DIR__, 3) . '/module.php';
    $module = require $modulePath;

    expect(file_exists($modulePath))->toBeTrue()
        ->and($module)->toBeArray()
        ->and($module)->toHaveKey('bindings')
        ->and($module['bindings'])->toHaveKey(AdminUserRepositoryInterface::class)
        ->and($module['bindings'])->toHaveKey(RoleRepositoryInterface::class)
        ->and($module['bindings'])->toHaveKey(PermissionRepositoryInterface::class)
        ->and($module['bindings'])->toHaveKey(UserProviderInterface::class)
        ->and($module['bindings'])->toHaveKey(AdminAuthConfigInterface::class)
        ->and($module['bindings'][AdminAuthConfigInterface::class])
            ->toBe(AdminAuthConfig::class);
});
