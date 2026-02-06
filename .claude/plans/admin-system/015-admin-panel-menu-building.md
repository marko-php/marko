# Task 015: marko/admin-panel - Menu Building and Section Rendering

**Status**: done
**Depends on**: 004, 014
**Retry count**: 0

## Description
Create the `AdminMenuBuilder` that constructs the sidebar navigation from registered admin sections and their menu items. It filters menu items based on the current user's permissions and sorts them by sort order. Also create the section listing for the dashboard.

## Context
- MenuBuilder reads from AdminSectionRegistry (populated by discovery)
- Filters menu items based on current user's permissions (from AdminUser::hasPermission)
- Sorts sections by sortOrder, then menu items within each section by their sortOrder
- Produces a structured array of menu data for the template
- Dashboard section listing shows all registered sections the user has access to
- The active menu item is determined by matching the current request path

## Requirements (Test Descriptions)
- [x] `it builds menu from registered admin sections`
- [x] `it sorts sections by sortOrder`
- [x] `it sorts menu items within each section by sortOrder`
- [x] `it filters out menu items the user lacks permission for`
- [x] `it shows all menu items for super admin users`
- [x] `it marks the active menu item based on current request path`
- [x] `it returns empty menu when no sections are registered`
- [x] `it builds dashboard section list filtered by user permissions`

## Acceptance Criteria
- All requirements have passing tests
- Menu builder is a standalone service injectable via DI
- Permission filtering uses existing hasPermission method
- Code follows code standards
