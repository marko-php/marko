# Plan: Plugin Method Naming Refactor

## Created
2026-03-25

## Status
completed

## Objective
Refactor the plugin system so method names match the target method exactly (no `before`/`after` prefix), with `#[Before]`/`#[After]` attributes determining timing. Add a `method` parameter to attributes as an explicit escape hatch for advanced cases.

## Scope

### In Scope
- Add `method` parameter to `#[Before]` and `#[After]` attributes
- Update `PluginDefinition` to store target method name separately from plugin method name
- Update `PluginDiscovery` to resolve target method from attribute `method` param or the plugin method's own name
- Update `PluginRegistry` to match by target method name (no more `before`/`after` prefix convention)
- Update `PluginProxy` to call the correct plugin method name (which may differ from target method name)
- Add validation: error when same plugin class has duplicate hooks on same target method without `method:` param
- Update all existing tests to use new naming convention
- Update docs page (`docs/src/content/docs/concepts/plugins.md`)
- Update architecture doc plugin section (`.claude/architecture.md`)

### Out of Scope
- External markotalk app (note: will need updating separately)
- Around plugins
- New plugin features beyond the naming refactor

## Success Criteria
- [ ] Plugin methods use target method name directly (e.g., `save()` not `beforeSave()`)
- [ ] `#[Before]`/`#[After]` attributes accept optional `method` parameter
- [ ] `method` parameter allows arbitrary plugin method names targeting any method
- [ ] Duplicate hook detection throws clear error
- [ ] All existing tests updated and passing
- [ ] New tests cover `method` parameter behavior
- [ ] Documentation fully updated with both standard and `method:` examples
- [ ] All tests passing with `./vendor/bin/pest --parallel`

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Add `method` param to Before/After attributes | - | completed |
| 002 | Refactor PluginDefinition, PluginDiscovery, and PluginRegistry (merged 002+003+004) | 001 | completed |
| 003 | ~~MERGED INTO 002~~ | - | merged |
| 004 | ~~MERGED INTO 002~~ | - | merged |
| 005 | Update PluginProxy to call correct plugin method name | 002 | completed |
| 006 | Add duplicate hook validation (intra-class + cross-class sort order) | 002 | completed |
| 007 | Update PluginInterceptorTest fixtures and tests | 002, 005 | completed |
| 008 | Update PluginDiscoveryTest, PluginRegistryTest, and ApplicationTest | 002 | completed |
| 009 | Update documentation | 007, 008 | completed |

## Architecture Notes

### Before (current)
```php
#[Plugin(target: MessageRepository::class)]
class MarkdownPlugin
{
    #[Before]
    public function beforeSave(Message $message): null { ... }
}
```

### After (new — standard)
```php
#[Plugin(target: MessageRepository::class)]
class MarkdownPlugin
{
    #[Before]
    public function save(Message $message): null { ... }
}
```

### After (new — explicit `method` param)
```php
#[Plugin(target: MessageRepository::class)]
class MarkdownPlugin
{
    #[Before(method: 'save')]
    public function validateInput(Message $message): null { ... }

    #[After(method: 'save')]
    public function enrichResult(mixed $result, Message $message): mixed { ... }
}
```

### Key Design Decisions
- **PluginDefinition change**: `beforeMethods`/`afterMethods` arrays change from `['beforeSave' => 10]` to storing both plugin method name and target method name: `['save' => ['pluginMethod' => 'save', 'sortOrder' => 10]]` keyed by target method
- **PluginRegistry change**: `getSortedMethodsFor()` no longer prepends `before`/`after` prefix — it matches by target method name directly, and returns the plugin method name for invocation
- **PluginProxy change**: Uses the plugin method name (from registry) to call the plugin, not the target method name
- **Validation**: If two methods in the same plugin class resolve to the same target method with the same timing (before/after), throw `PluginException`

## Risks & Mitigations
- **Breaking change for existing plugins**: This changes the naming convention. All existing plugins (including external markotalk app) will need updating. Mitigation: Document the migration path clearly.
- **Test fixture complexity**: Many test fixtures need renaming. Mitigation: Handle in dedicated test update tasks.
