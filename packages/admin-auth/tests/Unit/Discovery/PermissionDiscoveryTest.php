<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Tests\Unit\Discovery;

use Marko\Admin\Attributes\AdminPermission;
use Marko\Admin\Attributes\AdminSection;
use Marko\Admin\Contracts\AdminSectionInterface;
use Marko\Admin\Contracts\MenuItemInterface;
use Marko\Admin\Discovery\AdminSectionDiscovery;
use Marko\AdminAuth\Discovery\PermissionDiscovery;
use Marko\AdminAuth\PermissionRegistry;
use Marko\Core\Discovery\ClassFileParser;

it('discovers permissions from AdminPermission attributes on AdminSection classes', function (): void {
    $registry = new PermissionRegistry();
    $sectionDiscovery = new AdminSectionDiscovery(new ClassFileParser());
    $discovery = new PermissionDiscovery(
        registry: $registry,
        sectionDiscovery: $sectionDiscovery,
    );

    $discovery->discoverFromClass(DiscoverySectionWithPermissions::class);

    $permissions = $registry->all();
    expect($permissions)->toHaveCount(2)
        ->and($permissions[0]->key)->toBe('blog.posts.create')
        ->and($permissions[0]->label)->toBe('Create Posts')
        ->and($permissions[1]->key)->toBe('blog.posts.edit')
        ->and($permissions[1]->label)->toBe('Edit Posts');
});

it('derives group from first segment of permission key', function (): void {
    $registry = new PermissionRegistry();
    $sectionDiscovery = new AdminSectionDiscovery(new ClassFileParser());
    $discovery = new PermissionDiscovery(
        registry: $registry,
        sectionDiscovery: $sectionDiscovery,
    );

    $discovery->discoverFromClass(DiscoverySectionWithPermissions::class);

    $permissions = $registry->all();
    expect($permissions[0]->group)->toBe('blog')
        ->and($permissions[1]->group)->toBe('blog');
});

// Test fixture class
#[AdminSection(id: 'blog', label: 'Blog', icon: 'pencil', sortOrder: 10)]
#[AdminPermission(id: 'blog.posts.create', label: 'Create Posts')]
#[AdminPermission(id: 'blog.posts.edit', label: 'Edit Posts')]
class DiscoverySectionWithPermissions implements AdminSectionInterface
{
    public function getId(): string
    {
        return 'blog';
    }

    public function getLabel(): string
    {
        return 'Blog';
    }

    public function getIcon(): string
    {
        return 'pencil';
    }

    public function getSortOrder(): int
    {
        return 10;
    }

    /** @return array<MenuItemInterface> */
    public function getMenuItems(): array
    {
        return [];
    }
}
