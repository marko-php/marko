# Task 007: Hand-translate .twig templates into admin-panel-twig

**Status**: pending
**Depends on**: 002, 005
**Retry count**: 0

## Description
Write 5 `.twig` template files in `marko/admin-panel-twig` that are functionally equivalent to the `.latte` templates moved by task 006. Translation is manual — Latte and Twig syntax diverge enough that mechanical conversion isn't viable. The HTML output produced by each Twig template must be equivalent to its Latte counterpart for the same input variables.

## Context
- Files to create (mirror the structure in admin-panel-latte):
  - `packages/admin-panel-twig/resources/views/auth/login.twig`
  - `packages/admin-panel-twig/resources/views/layout/base.twig`
  - `packages/admin-panel-twig/resources/views/dashboard/index.twig`
  - `packages/admin-panel-twig/resources/views/partials/sidebar.twig`
  - `packages/admin-panel-twig/resources/views/partials/flash.twig`
- Source-of-truth Latte templates: `packages/admin-panel-latte/resources/views/*.latte` (after task 006)

**Syntax translation reference (apply per file):**

| Latte construct | Twig equivalent |
|-----------------|-----------------|
| `{default $pageTitle = 'X'}` | `{% set pageTitle = pageTitle\|default('X') %}` (per variable) |
| `{$variable}` | `{{ variable }}` |
| `<div n:if="$x">...</div>` | `{% if x %}<div>...</div>{% endif %}` |
| `{include 'admin-panel::partials/sidebar'}` | `{% include 'admin-panel::partials/sidebar' %}` |
| `{layout 'admin-panel::layout/base'}` | `{% extends 'admin-panel::layout/base' %}` |
| `{block content}...{/block}` | `{% block content %}...{% endblock %}` |
| `{foreach $items as $item}...{/foreach}` | `{% for item in items %}...{% endfor %}` |
| `{$item->getLabel()}` | `{{ item.getLabel() }}` (or `{{ item.label }}` if Twig's property accessor finds it) |

**Output equivalence contract:**
- Same DOCTYPE, same elements, same form structure, same data flow
- Same HTML class names, IDs, attributes
- Same conditional rendering behavior (e.g., the error div only renders when `error` is truthy)
- Same CSRF token output (`{$csrfToken}` → `{{ csrfToken }}`)
- Same iteration behavior in sidebar partial (renders each menu item with label/url)

**Important — autoescape consideration:**
view-twig sets `strict_variables: true` and `autoescape: 'html'` by default. The Latte templates may rely on Latte's escaping behavior. When in doubt, default to escaped output (`{{ variable }}`) unless the Latte original explicitly uses an `|noescape` filter.

## Requirements (Test Descriptions)
- [ ] `auth/login.twig exists and renders a form with email and password fields`
- [ ] `layout/base.twig exists and contains HTML doctype, sidebar include, and content block`
- [ ] `dashboard/index.twig exists and extends layout/base.twig with a content block`
- [ ] `partials/sidebar.twig exists and iterates menu items`
- [ ] `partials/flash.twig exists and renders success and error message states`
- [ ] `login.twig includes a CSRF hidden input with name _token`
- [ ] `the layout's content block can be overridden by child templates (verified via dashboard.twig)`

## Acceptance Criteria
- All 5 `.twig` files exist at the specified paths
- Each template produces HTML structurally equivalent to its Latte counterpart for the same input data
- Templates use Twig 3 syntax (no Twig 1/2 deprecated constructs)
- No autoescape-disabling filters (`|raw`) unless the Latte original explicitly opted out of escaping
- Code follows project standards (Twig templates have no PHP standards to follow, but file extensions and naming match)
- Task 008 writes the test file separately; this task focuses purely on template creation
