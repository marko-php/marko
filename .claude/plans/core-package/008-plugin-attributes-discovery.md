# Task 008: Plugin Attributes and Discovery

**Status**: pending
**Depends on**: 002, 005
**Retry count**: 0

## Description
Create the plugin attributes (`#[Plugin]`, `#[Before]`, `#[After]`) and the discovery system that finds plugin classes in loaded modules. Plugins allow modifying behavior of any public method without touching source code.

## Context
- Location: `packages/core/src/Attributes/` and `packages/core/src/Plugin/`
- #[Plugin] on class declares what class it plugins
- #[Before] on method runs before target method (can short-circuit)
- #[After] on method runs after target method (can modify result)
- Sort order is per-method, not per-class

## Requirements (Test Descriptions)
- [ ] `it creates Plugin attribute with target class parameter`
- [ ] `it creates Before attribute with optional sortOrder parameter`
- [ ] `it creates After attribute with optional sortOrder parameter`
- [ ] `it defaults sortOrder to 0 when not specified`
- [ ] `it discovers plugin classes in module src directories`
- [ ] `it extracts target class from Plugin attribute`
- [ ] `it extracts before methods with their sort orders`
- [ ] `it extracts after methods with their sort orders`
- [ ] `it throws PluginException when Plugin attribute missing target`
- [ ] `it throws PluginException when before/after method on non-plugin class`
- [ ] `it collects all plugins for a given target class`
- [ ] `it sorts plugins by sortOrder (lower runs first)`

## Acceptance Criteria
- All requirements have passing tests
- Attributes are simple and declarative
- Discovery handles nested namespaces in module src/
- Code follows strict types declaration

## Files to Create
```
packages/core/src/Attributes/
  Plugin.php                # #[Plugin(TargetClass::class)]
  Before.php                # #[Before(sortOrder: 10)]
  After.php                 # #[After(sortOrder: 20)]
packages/core/src/Plugin/
  PluginDefinition.php      # Value object for discovered plugin
  PluginDiscovery.php       # Finds plugin classes in modules
  PluginRegistry.php        # Stores plugins indexed by target class
```

## Implementation Notes
(Left blank - filled in by programmer during implementation)
