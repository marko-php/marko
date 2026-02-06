# Task 002: marko/admin Contracts - AdminSection, Menu, Permissions

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create the `marko/admin` package with the core contract layer. This defines what it means to be an admin section, how menu items are structured, and how permissions are declared. All other admin packages depend on these contracts.

## Context
- New package at `packages/admin/`
- Follow existing package patterns: `composer.json`, `module.php`, `src/`, `tests/`
- Namespace: `Marko\Admin`
- Depends on: `marko/core`, `marko/routing`
- This is the interface package - no implementations of admin UI or auth here
- Attributes follow existing patterns in `packages/core/src/Attributes/`

## Requirements (Test Descriptions)
- [ ] `it defines AdminSectionInterface with getId, getLabel, getIcon, getSortOrder, getMenuItems methods`
- [ ] `it defines MenuItemInterface with getId, getLabel, getUrl, getIcon, getSortOrder, getPermission methods`
- [ ] `it defines DashboardWidgetInterface with getId, getLabel, getSortOrder, render methods`
- [ ] `it creates AdminSection attribute targeting classes with id, label, icon, sortOrder properties`
- [ ] `it creates AdminPermission attribute targeting classes with repeatable support for id and label`
- [ ] `it creates MenuItem value object implementing MenuItemInterface`
- [ ] `it creates AdminSectionRegistryInterface with register, all, get methods`
- [ ] `it creates AdminSectionRegistry implementing AdminSectionRegistryInterface`
- [ ] `it registers sections and retrieves them sorted by sortOrder`
- [ ] `it throws AdminException when registering duplicate section id`
- [ ] `it has valid composer.json with correct dependencies and autoload`

## Acceptance Criteria
- All requirements have passing tests
- Package follows existing composer.json patterns
- All interfaces are in `Contracts/` subdirectory
- Attributes are in `Attributes/` subdirectory
- Code follows code standards
