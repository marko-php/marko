# Task 019: marko/blog Admin - BlogAdminSection Registration and Permissions

**Status**: pending
**Depends on**: 004, 007
**Retry count**: 0

## Description
Register the blog package's admin presence by creating `BlogAdminSection` with `#[AdminSection]` and `#[AdminPermission]` attributes. This declares what the blog contributes to the admin panel: its menu items and the permissions it needs.

## Context
- BlogAdminSection goes in `packages/blog/src/Admin/BlogAdminSection.php`
- Blog adds `marko/admin` as a dependency in composer.json
- Menu items: Posts (list, create), Authors (list, create), Categories (list, create), Tags (list, create), Comments (list)
- Permissions declared:
  - `blog.posts.view`, `blog.posts.create`, `blog.posts.edit`, `blog.posts.delete`, `blog.posts.publish`
  - `blog.authors.view`, `blog.authors.create`, `blog.authors.edit`, `blog.authors.delete`
  - `blog.categories.view`, `blog.categories.create`, `blog.categories.edit`, `blog.categories.delete`
  - `blog.tags.view`, `blog.tags.create`, `blog.tags.edit`, `blog.tags.delete`
  - `blog.comments.view`, `blog.comments.edit`, `blog.comments.delete`
- Section: id=`blog`, label=`Blog`, icon=`newspaper`, sortOrder=50

## Requirements (Test Descriptions)
- [ ] `it creates BlogAdminSection implementing AdminSectionInterface`
- [ ] `it has AdminSection attribute with id blog, label Blog, icon newspaper, sortOrder 50`
- [ ] `it declares all blog post permissions via AdminPermission attributes`
- [ ] `it declares all blog author permissions via AdminPermission attributes`
- [ ] `it declares all blog category permissions via AdminPermission attributes`
- [ ] `it declares all blog tag permissions via AdminPermission attributes`
- [ ] `it declares all blog comment permissions via AdminPermission attributes`
- [ ] `it returns menu items for posts, authors, categories, tags, comments`
- [ ] `it sets correct permission on each menu item`
- [ ] `it sorts menu items with posts first`

## Acceptance Criteria
- All requirements have passing tests
- Blog package adds marko/admin dependency
- AdminSection follows the contract interfaces exactly
- All permissions follow the `blog.{entity}.{action}` naming convention
- Code follows code standards
