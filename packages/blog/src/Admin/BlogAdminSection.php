<?php

declare(strict_types=1);

namespace Marko\Blog\Admin;

use Marko\Admin\Attributes\AdminPermission;
use Marko\Admin\Attributes\AdminSection;
use Marko\Admin\Contracts\AdminSectionInterface;
use Marko\Admin\Contracts\MenuItemInterface;
use Marko\Admin\MenuItem;

#[AdminSection(
    id: 'blog',
    label: 'Blog',
    icon: 'newspaper',
    sortOrder: 50,
)]
#[AdminPermission(id: 'blog.posts.view', label: 'View Posts')]
#[AdminPermission(id: 'blog.posts.create', label: 'Create Posts')]
#[AdminPermission(id: 'blog.posts.edit', label: 'Edit Posts')]
#[AdminPermission(id: 'blog.posts.delete', label: 'Delete Posts')]
#[AdminPermission(id: 'blog.posts.publish', label: 'Publish Posts')]
#[AdminPermission(id: 'blog.authors.view', label: 'View Authors')]
#[AdminPermission(id: 'blog.authors.create', label: 'Create Authors')]
#[AdminPermission(id: 'blog.authors.edit', label: 'Edit Authors')]
#[AdminPermission(id: 'blog.authors.delete', label: 'Delete Authors')]
#[AdminPermission(id: 'blog.categories.view', label: 'View Categories')]
#[AdminPermission(id: 'blog.categories.create', label: 'Create Categories')]
#[AdminPermission(id: 'blog.categories.edit', label: 'Edit Categories')]
#[AdminPermission(id: 'blog.categories.delete', label: 'Delete Categories')]
#[AdminPermission(id: 'blog.tags.view', label: 'View Tags')]
#[AdminPermission(id: 'blog.tags.create', label: 'Create Tags')]
#[AdminPermission(id: 'blog.tags.edit', label: 'Edit Tags')]
#[AdminPermission(id: 'blog.tags.delete', label: 'Delete Tags')]
#[AdminPermission(id: 'blog.comments.view', label: 'View Comments')]
#[AdminPermission(id: 'blog.comments.edit', label: 'Edit Comments')]
#[AdminPermission(id: 'blog.comments.delete', label: 'Delete Comments')]
class BlogAdminSection implements AdminSectionInterface
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
        return 'newspaper';
    }

    public function getSortOrder(): int
    {
        return 50;
    }

    /**
     * @return array<MenuItemInterface>
     */
    public function getMenuItems(): array
    {
        return [
            new MenuItem(
                id: 'blog.posts',
                label: 'Posts',
                url: '/admin/blog/posts',
                icon: 'file-text',
                sortOrder: 10,
                permission: 'blog.posts.view',
            ),
            new MenuItem(
                id: 'blog.authors',
                label: 'Authors',
                url: '/admin/blog/authors',
                icon: 'users',
                sortOrder: 20,
                permission: 'blog.authors.view',
            ),
            new MenuItem(
                id: 'blog.categories',
                label: 'Categories',
                url: '/admin/blog/categories',
                icon: 'folder',
                sortOrder: 30,
                permission: 'blog.categories.view',
            ),
            new MenuItem(
                id: 'blog.tags',
                label: 'Tags',
                url: '/admin/blog/tags',
                icon: 'tag',
                sortOrder: 40,
                permission: 'blog.tags.view',
            ),
            new MenuItem(
                id: 'blog.comments',
                label: 'Comments',
                url: '/admin/blog/comments',
                icon: 'message-circle',
                sortOrder: 50,
                permission: 'blog.comments.view',
            ),
        ];
    }
}
