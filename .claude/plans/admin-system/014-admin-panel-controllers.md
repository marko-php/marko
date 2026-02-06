# Task 014: marko/admin-panel - AdminPanelController (Dashboard, Login, Logout)

**Status**: pending
**Depends on**: 011, 013
**Retry count**: 0

## Description
Create the core admin panel controllers: `DashboardController` (admin home page), `LoginController` (login form + authentication), and handle logout. These controllers use the admin guard and render Latte templates.

## Context
- Controllers follow blog controller patterns: constructor injection, route attributes
- Routes: `GET /admin` (dashboard), `GET /admin/login` (login form), `POST /admin/login` (authenticate), `POST /admin/logout` (logout)
- DashboardController requires AdminAuthMiddleware
- LoginController uses GuestMiddleware (from auth) configured for admin guard
- Login uses AuthManager with admin guard to attempt authentication
- After successful login, redirect to dashboard
- After logout, redirect to login
- Dashboard displays registered admin sections from AdminSectionRegistry

## Requirements (Test Descriptions)
- [ ] `it renders dashboard template with registered sections on GET /admin`
- [ ] `it requires authentication via AdminAuthMiddleware for dashboard`
- [ ] `it renders login form on GET /admin/login`
- [ ] `it redirects authenticated users from login page to dashboard`
- [ ] `it authenticates user on POST /admin/login with valid credentials`
- [ ] `it redirects to dashboard after successful login`
- [ ] `it returns to login with error on invalid credentials`
- [ ] `it logs out user on POST /admin/logout and redirects to login`
- [ ] `it passes admin sections to dashboard template for display`
- [ ] `it passes current user to base layout template`

## Acceptance Criteria
- All requirements have passing tests
- Controllers follow existing controller patterns
- Route attributes match admin route prefix configuration
- Auth uses admin guard specifically
- Code follows code standards
