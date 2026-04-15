# Task 005: Prevent Plugins from Targeting Plugin Classes

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Add validation to `PluginRegistry::register()` that prevents registering a plugin whose target class is itself a plugin (has the `#[Plugin]` attribute). This enforces the architectural rule that plugins modify business logic on service classes, not other plugins. If someone needs to alter a plugin's behavior, they should use a Preference to replace the plugin class entirely.

## Context
- Related files: `packages/core/src/Plugin/PluginRegistry.php`, `packages/core/src/Exceptions/PluginException.php`
- PluginRegistry already validates conflicting sort orders and duplicate hooks during `register()`
- Use reflection to check if the target class has a `#[Plugin]` attribute — this is order-independent (doesn't matter which plugin is registered first)
- Add a new static factory method to `PluginException` for this case (e.g., `PluginException::cannotTargetPlugin()`)
- The error message should be helpful: explain that plugins cannot target other plugin classes, and suggest using a Preference to replace the plugin instead

## Requirements (Test Descriptions)
- [ ] `it throws PluginException when registering a plugin that targets another plugin class`
- [ ] `it includes helpful message suggesting Preference as alternative`
- [ ] `it allows registering plugins that target non-plugin classes`

## Acceptance Criteria
- All requirements have passing tests
- Error message follows Marko's "loud errors with helpful messages" principle
- Code follows code standards
