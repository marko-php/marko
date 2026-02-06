# Task 013: marko/admin-panel - Layout Template and Latte Views

**Status**: completed
**Depends on**: 012
**Retry count**: 0

## Description
Create the Latte template files for the admin panel: base layout with sidebar navigation, login page, and dashboard page. These are the foundational templates that all admin sections will extend.

## Context
- Templates go in `packages/admin-panel/resources/views/`
- Template naming: `admin-panel::layout/base`, `admin-panel::auth/login`, `admin-panel::dashboard/index`
- Base layout includes: HTML shell, sidebar nav (populated from menu items), content area, flash messages
- Login page: simple email/password form posting to admin login route
- Dashboard page: extends base layout, shows welcome message and registered sections summary
- Templates should be clean, semantic HTML with minimal inline styles (CSS can be a basic embedded stylesheet)
- Latte syntax: `{block content}`, `{include}`, `{$variable}`, `{if}`, `{foreach}`, `n:href`, etc.
- No JavaScript framework dependencies - plain HTML forms

## Requirements (Test Descriptions)
- [x] `it creates base layout template with html shell, sidebar, and content block`
- [x] `it creates login template with email and password form fields`
- [x] `it creates dashboard template extending base layout`
- [x] `it creates sidebar partial with menu items loop`
- [x] `it creates flash message partial for success and error messages`
- [x] `it includes csrf-safe form structure in login template`
- [x] `it has content block that child templates can override`

## Acceptance Criteria
- All templates are valid Latte syntax
- Base layout has clear extension points (blocks)
- Templates are semantic HTML
- Login form posts to configurable admin route
- Code follows code standards
