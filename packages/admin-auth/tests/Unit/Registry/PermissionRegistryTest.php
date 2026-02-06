<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Tests\Unit\Registry;

use Marko\AdminAuth\Contracts\PermissionRegistryInterface;
use Marko\AdminAuth\Exceptions\AdminAuthException;
use Marko\AdminAuth\PermissionRegistry;

it('registers permissions with key, label, and group', function (): void {
    $registry = new PermissionRegistry();

    $registry->register(
        key: 'blog.posts.create',
        label: 'Create Posts',
        group: 'blog',
    );

    expect($registry)->toBeInstanceOf(PermissionRegistryInterface::class);

    $permissions = $registry->all();
    expect($permissions)->toHaveCount(1)
        ->and($permissions[0]->key)->toBe('blog.posts.create')
        ->and($permissions[0]->label)->toBe('Create Posts')
        ->and($permissions[0]->group)->toBe('blog');
});

it('retrieves all registered permissions', function (): void {
    $registry = new PermissionRegistry();

    $registry->register(
        key: 'blog.posts.create',
        label: 'Create Posts',
        group: 'blog',
    );

    $registry->register(
        key: 'blog.posts.edit',
        label: 'Edit Posts',
        group: 'blog',
    );

    $registry->register(
        key: 'analytics.reports.view',
        label: 'View Reports',
        group: 'analytics',
    );

    $permissions = $registry->all();
    expect($permissions)->toHaveCount(3)
        ->and($permissions[0]->key)->toBe('blog.posts.create')
        ->and($permissions[1]->key)->toBe('blog.posts.edit')
        ->and($permissions[2]->key)->toBe('analytics.reports.view');
});

it('retrieves permissions filtered by group', function (): void {
    $registry = new PermissionRegistry();

    $registry->register(
        key: 'blog.posts.create',
        label: 'Create Posts',
        group: 'blog',
    );

    $registry->register(
        key: 'blog.posts.edit',
        label: 'Edit Posts',
        group: 'blog',
    );

    $registry->register(
        key: 'analytics.reports.view',
        label: 'View Reports',
        group: 'analytics',
    );

    $blogPermissions = $registry->getByGroup('blog');
    expect($blogPermissions)->toHaveCount(2)
        ->and($blogPermissions[0]->key)->toBe('blog.posts.create')
        ->and($blogPermissions[1]->key)->toBe('blog.posts.edit');

    $analyticsPermissions = $registry->getByGroup('analytics');
    expect($analyticsPermissions)->toHaveCount(1)
        ->and($analyticsPermissions[0]->key)->toBe('analytics.reports.view');
});

it('throws AdminAuthException when registering duplicate permission key', function (): void {
    $registry = new PermissionRegistry();

    $registry->register(
        key: 'blog.posts.create',
        label: 'Create Posts',
        group: 'blog',
    );

    $registry->register(
        key: 'blog.posts.create',
        label: 'Create Posts Duplicate',
        group: 'blog',
    );
})->throws(AdminAuthException::class, "Permission with key 'blog.posts.create' is already registered");

it('supports wildcard permission matching with asterisk', function (): void {
    $registry = new PermissionRegistry();

    expect($registry->matches('blog.*', 'blog.posts.create'))->toBeTrue()
        ->and($registry->matches('blog.*', 'blog.posts.edit'))->toBeTrue()
        ->and($registry->matches('*', 'anything.at.all'))->toBeTrue();
});

it('matches blog.* against blog.posts.create', function (): void {
    $registry = new PermissionRegistry();

    expect($registry->matches('blog.*', 'blog.posts.create'))->toBeTrue();
});

it('does not match blog.* against analytics.reports.view', function (): void {
    $registry = new PermissionRegistry();

    expect($registry->matches('blog.*', 'analytics.reports.view'))->toBeFalse();
});

it('matches single asterisk against any permission', function (): void {
    $registry = new PermissionRegistry();

    expect($registry->matches('*', 'blog.posts.create'))->toBeTrue()
        ->and($registry->matches('*', 'analytics.reports.view'))->toBeTrue()
        ->and($registry->matches('*', 'system.config.edit'))->toBeTrue();
});
