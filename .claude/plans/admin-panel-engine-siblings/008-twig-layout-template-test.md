# Task 008: Write LayoutTemplateTest equivalent for Twig templates

**Status**: completed
**Depends on**: 007
**Retry count**: 0

## Description
Create `packages/admin-panel-twig/tests/LayoutTemplateTest.php` mirroring the assertions in `packages/admin-panel-latte/tests/LayoutTemplateTest.php` but adapted for Twig syntax. Verifies each of the 5 Twig templates contains the expected structural elements (DOCTYPE, form fields, blocks, includes, etc.).

## Context
- New file: `packages/admin-panel-twig/tests/LayoutTemplateTest.php`
- Reference: `packages/admin-panel-latte/tests/LayoutTemplateTest.php` (after task 006 moves it)
- Namespace: `Marko\AdminPanel\Twig\Tests`
- Path resolution: `$viewsPath = dirname(__DIR__) . '/resources/views'` (one level up since test is at `tests/LayoutTemplateTest.php`, templates at `resources/views/`)

**Assertion translations:**

| Latte test assertion | Twig equivalent |
|----------------------|-----------------|
| `->toContain('{include')` | `->toContain('{% include')` |
| `->toContain('{block content}')` | `->toContain('{% block content %}')` |
| `->toContain('{layout')` | `->toContain('{% extends')` |
| `->toContain('{foreach')` | `->toContain('{% for')` |
| `->toContain('$flashMessages')` | `->toContain('flashMessages')` (no `$` in Twig) |
| `->toContain('$csrfToken')` | `->toContain('csrfToken')` |
| `->toContain('{$pageTitle}')` | `->toContain('{{ pageTitle')` |

**Pure HTML/attribute assertions (`<form>`, `method="post"`, `type="email"`, etc.) carry over unchanged** — those are not template-engine-specific.

## Requirements (Test Descriptions)
- [ ] `it creates base layout template with html shell, sidebar, and content block`
- [ ] `it creates login template with email and password form fields`
- [ ] `it creates dashboard template extending base layout`
- [ ] `it creates sidebar partial with menu items loop`
- [ ] `it creates flash message partial for success and error messages`
- [ ] `it includes csrf-safe form structure in login template`
- [ ] `it has content block that child templates can override`

(Same test names as the Latte version — the assertions inside differ to match Twig syntax.)

## Acceptance Criteria
- `packages/admin-panel-twig/tests/LayoutTemplateTest.php` exists
- All 7 tests pass against the templates created in task 007
- Test assertions are the Twig-syntax equivalent of the Latte test's assertions
- Namespace matches package: `Marko\AdminPanel\Twig\Tests`
- Code follows test standards (expectation chaining with `->and()`, `->toBeTrue()`, etc.)
