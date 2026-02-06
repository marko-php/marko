<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Unit\Admin;

use Marko\Admin\Attributes\AdminPermission;
use Marko\Admin\Attributes\AdminSection;
use Marko\Admin\Contracts\AdminSectionInterface;
use Marko\Admin\Contracts\MenuItemInterface;
use Marko\Blog\Admin\BlogAdminSection;
use ReflectionClass;

it('creates BlogAdminSection implementing AdminSectionInterface', function (): void {
    $section = new BlogAdminSection();

    expect($section)->toBeInstanceOf(AdminSectionInterface::class);
});

it('has AdminSection attribute with id blog, label Blog, icon newspaper, sortOrder 50', function (): void {
    $reflection = new ReflectionClass(BlogAdminSection::class);
    $attributes = $reflection->getAttributes(AdminSection::class);

    expect($attributes)->toHaveCount(1);

    $adminSection = $attributes[0]->newInstance();

    expect($adminSection->id)->toBe('blog')
        ->and($adminSection->label)->toBe('Blog')
        ->and($adminSection->icon)->toBe('newspaper')
        ->and($adminSection->sortOrder)->toBe(50);
});

it('declares all blog post permissions via AdminPermission attributes', function (): void {
    $reflection = new ReflectionClass(BlogAdminSection::class);
    $attributes = $reflection->getAttributes(AdminPermission::class);
    $permissions = array_map(
        fn ($attr) => $attr->newInstance(),
        $attributes,
    );
    $permissionIds = array_map(fn ($p) => $p->id, $permissions);

    expect($permissionIds)->toContain('blog.posts.view')
        ->and($permissionIds)->toContain('blog.posts.create')
        ->and($permissionIds)->toContain('blog.posts.edit')
        ->and($permissionIds)->toContain('blog.posts.delete')
        ->and($permissionIds)->toContain('blog.posts.publish');
});

it('declares all blog author permissions via AdminPermission attributes', function (): void {
    $reflection = new ReflectionClass(BlogAdminSection::class);
    $attributes = $reflection->getAttributes(AdminPermission::class);
    $permissions = array_map(
        fn ($attr) => $attr->newInstance(),
        $attributes,
    );
    $permissionIds = array_map(fn ($p) => $p->id, $permissions);

    expect($permissionIds)->toContain('blog.authors.view')
        ->and($permissionIds)->toContain('blog.authors.create')
        ->and($permissionIds)->toContain('blog.authors.edit')
        ->and($permissionIds)->toContain('blog.authors.delete');
});

it('declares all blog category permissions via AdminPermission attributes', function (): void {
    $reflection = new ReflectionClass(BlogAdminSection::class);
    $attributes = $reflection->getAttributes(AdminPermission::class);
    $permissions = array_map(
        fn ($attr) => $attr->newInstance(),
        $attributes,
    );
    $permissionIds = array_map(fn ($p) => $p->id, $permissions);

    expect($permissionIds)->toContain('blog.categories.view')
        ->and($permissionIds)->toContain('blog.categories.create')
        ->and($permissionIds)->toContain('blog.categories.edit')
        ->and($permissionIds)->toContain('blog.categories.delete');
});

it('declares all blog tag permissions via AdminPermission attributes', function (): void {
    $reflection = new ReflectionClass(BlogAdminSection::class);
    $attributes = $reflection->getAttributes(AdminPermission::class);
    $permissions = array_map(
        fn ($attr) => $attr->newInstance(),
        $attributes,
    );
    $permissionIds = array_map(fn ($p) => $p->id, $permissions);

    expect($permissionIds)->toContain('blog.tags.view')
        ->and($permissionIds)->toContain('blog.tags.create')
        ->and($permissionIds)->toContain('blog.tags.edit')
        ->and($permissionIds)->toContain('blog.tags.delete');
});

it('declares all blog comment permissions via AdminPermission attributes', function (): void {
    $reflection = new ReflectionClass(BlogAdminSection::class);
    $attributes = $reflection->getAttributes(AdminPermission::class);
    $permissions = array_map(
        fn ($attr) => $attr->newInstance(),
        $attributes,
    );
    $permissionIds = array_map(fn ($p) => $p->id, $permissions);

    expect($permissionIds)->toContain('blog.comments.view')
        ->and($permissionIds)->toContain('blog.comments.edit')
        ->and($permissionIds)->toContain('blog.comments.delete');
});

it('returns menu items for posts, authors, categories, tags, comments', function (): void {
    $section = new BlogAdminSection();
    $menuItems = $section->getMenuItems();

    expect($menuItems)->toHaveCount(5);

    $labels = array_map(fn (MenuItemInterface $item) => $item->getLabel(), $menuItems);

    expect($labels)->toContain('Posts')
        ->and($labels)->toContain('Authors')
        ->and($labels)->toContain('Categories')
        ->and($labels)->toContain('Tags')
        ->and($labels)->toContain('Comments');

    foreach ($menuItems as $item) {
        expect($item)->toBeInstanceOf(MenuItemInterface::class);
    }
});

it('sets correct permission on each menu item', function (): void {
    $section = new BlogAdminSection();
    $menuItems = $section->getMenuItems();

    $permissionsByLabel = [];
    foreach ($menuItems as $item) {
        $permissionsByLabel[$item->getLabel()] = $item->getPermission();
    }

    expect($permissionsByLabel['Posts'])->toBe('blog.posts.view')
        ->and($permissionsByLabel['Authors'])->toBe('blog.authors.view')
        ->and($permissionsByLabel['Categories'])->toBe('blog.categories.view')
        ->and($permissionsByLabel['Tags'])->toBe('blog.tags.view')
        ->and($permissionsByLabel['Comments'])->toBe('blog.comments.view');
});

it('sorts menu items with posts first', function (): void {
    $section = new BlogAdminSection();
    $menuItems = $section->getMenuItems();

    // Posts should have the lowest sort order (first)
    expect($menuItems[0]->getLabel())->toBe('Posts')
        ->and($menuItems[0]->getSortOrder())->toBe(10);

    // Verify all sort orders are in ascending order
    $previousSortOrder = 0;
    foreach ($menuItems as $item) {
        expect($item->getSortOrder())->toBeGreaterThan($previousSortOrder);
        $previousSortOrder = $item->getSortOrder();
    }
});
