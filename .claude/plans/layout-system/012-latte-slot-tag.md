# Task 012: Latte `{slot}` Custom Tag Extension

**Status**: completed
**Depends on**: 010
**Retry count**: 0

## Description
Add a `{slot name}{/slot}` custom Latte tag to the `view-latte` package. This tag outputs the pre-rendered HTML for a named slot assembled by the `LayoutProcessor`. The slot data is passed to the template as a `$slots` variable (associative array of slot name → rendered HTML string).

## Context
- Related files: `packages/view-latte/src/LatteEngineFactory.php` (where extensions are registered), `packages/view-latte/src/LatteView.php`
- Latte 3 extension system: create an `Extension` class, register tags via `getTags()`
- The `{slot content}{/slot}` tag outputs `$slots['content']` if it exists
- If the slot has no content (no components rendered into it), output empty string
- The `{/slot}` closing tag is needed for template readability but the tag is not a block -- it just outputs pre-rendered HTML
- This is the only template-engine-specific integration point -- future Blade/Twig drivers add their own equivalent
- **Important:** The `{slot}` tag has ZERO imports from `Marko\Layout`. It simply outputs `$slots[$name] ?? ''` from the template variables already passed by `LayoutProcessor`. This means the extension lives in `packages/view-latte/` and `view-latte` does NOT need to depend on `marko/layout`. The extension class and its registration in `LatteEngineFactory` are the only changes to `view-latte`.
- Alternative: templates can use `{$slots['content']}` directly without the custom tag, but `{slot content}{/slot}` is cleaner and more readable.

## Requirements (Test Descriptions)
- [ ] `it registers the slot tag with the Latte engine`
- [ ] `it outputs pre-rendered HTML for a named slot`
- [ ] `it outputs empty string when slot has no content`
- [ ] `it works with multiple slots in the same template`
- [ ] `it handles nested slot names with dot-notation`
- [ ] `it integrates with LatteEngineFactory for automatic registration`

## Acceptance Criteria
- All requirements have passing tests
- Follows Latte 3 extension API
- Registered automatically when view-latte is installed
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
