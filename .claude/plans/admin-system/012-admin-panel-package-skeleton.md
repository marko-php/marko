# Task 012: marko/admin-panel - Package Skeleton and AdminPanelConfig

**Status**: completed
**Depends on**: 003
**Retry count**: 0

## Description
Create the `marko/admin-panel` package with its skeleton: composer.json, module.php, config, and AdminPanelConfig class. This package provides the server-rendered admin UI using Latte templates.

## Context
- New package at `packages/admin-panel/`
- Namespace: `Marko\AdminPanel`
- Depends on: `marko/admin`, `marko/admin-auth`, `marko/view`, `marko/auth`, `marko/session`, `marko/routing`
- Config: `admin-panel.page_title` (default: `'Marko Admin'`), `admin-panel.items_per_page` (default: 20)
- Templates will be in `resources/views/` directory (resolved via `admin-panel::` prefix)
- module.php with bindings for AdminPanelConfig

## Requirements (Test Descriptions)
- [ ] `it has valid composer.json with admin, admin-auth, view, auth, session, routing dependencies`
- [ ] `it creates AdminPanelConfig with pageTitle and itemsPerPage settings`
- [ ] `it provides default page title of Marko Admin`
- [ ] `it provides default items per page of 20`
- [ ] `it has valid config/admin-panel.php with default values`
- [ ] `it binds AdminPanelConfigInterface to AdminPanelConfig in module.php`

## Acceptance Criteria
- All requirements have passing tests
- Package structure matches existing packages
- Config follows existing patterns
- Code follows code standards
