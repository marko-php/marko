---
name: create-plugin
description: >
  Create a Marko plugin — a class that intercepts methods on another class to modify
  behavior without subclassing. **Use this skill whenever the user wants to add a
  plugin, intercept a method, or extend a class's behavior.** Concrete triggers:
  "add a plugin for the OrderService", "intercept the save method on User", "extend
  the behavior of X without changing it", "modify Y's return value before it's
  returned", "I need a plugin", "create a Before/After plugin". Plugins use the
  #[Plugin], #[Before], #[After] attributes.
---

# Create a Marko Plugin

> **This skill is the canonical specification for a Marko plugin. Do not inspect existing plugins in this project to infer structure — siblings may have drifted from spec. Copy the templates from `assets/` verbatim, substitute placeholders, and stop.**

A plugin is a class that intercepts the input or output of a public method on any
other class — without replacing that class. Plugins are Marko's fine-grained
extensibility primitive. They are auto-discovered from any module's `src/`
directory; **no manual registration is needed**.

## When to use

Use a plugin when the user wants to modify arguments to, or the result from, a
public method on a class without rewriting or subclassing it. Common cases:

- Enrich a return value
- Validate or transform inputs before the method runs
- Short-circuit (cache hit, guard, redirect)
- Log or observe calls
- Chain transformations across modules

For **total replacement** of a class, use a Preference instead — that is a separate
skill (`marko-create-preference`). Plugins and Preferences are complementary; only
Preferences swap entire implementations.

## Plugin model — two timings only

Marko supports exactly **two** plugin types. A third type that wraps the call is
**intentionally absent** — anything it could do is expressed as a Before
(short-circuit) or After (result transformation), keeping the call stack
debuggable.

| Attribute   | When                       | What it does                                  |
|-------------|----------------------------|-----------------------------------------------|
| `#[Before]` | Before the target method   | Modify args, short-circuit, or pass through   |
| `#[After]`  | After the target method    | Receive and modify the return value           |

### Before return semantics

| Return value            | Effect                                                    |
|-------------------------|-----------------------------------------------------------|
| `null`                  | Pass-through — original method runs with original args    |
| `array`                 | Replace arguments — original method runs with these args  |
| Any other non-null value| Short-circuit — original method is NOT called             |

### After return semantics

After plugins receive `$result` (the return value of the original method or a
prior After plugin) as their first parameter, followed by the (possibly modified)
original arguments. Each After plugin's return value feeds the next After plugin
in sort order — always return the (possibly modified) result.

### sortOrder

`sortOrder` is defined on the method-level attribute, **not** the class. Lower
numbers run first; negatives are valid; default is `0`.

```php
#[Before(sortOrder: -10)]   // runs before plugins at default 0
#[After(sortOrder: 100)]    // runs after lower-priority Afters
```

## Runbook

### Step 1 — Identify the target

Determine which class and public method to intercept. Plugins cannot intercept
`protected` or `private` methods, and the target class must not be `final`.

### Step 2 — Copy the plugin class template

Copy `assets/PluginClass.php.tmpl` verbatim. Substitute:

| Placeholder       | Value                                  |
|-------------------|----------------------------------------|
| `{{Vendor}}`      | Composer vendor namespace (e.g., `App`) |
| `{{Name}}`        | Module name (e.g., `Blog`)             |
| `{{TargetClass}}` | Unqualified class name (e.g., `PostRepository`) |

Place the file at `src/Plugins/{{TargetClass}}Plugin.php` inside the module.

Add the correct `use` statement for the fully-qualified target class.

Remove any `#[Before]` or `#[After]` methods that are not needed for this plugin —
the template shows both for illustration.

### Step 3 — Implement the interceptor methods

Name each plugin method identically to the target method it intercepts (or use the
`method:` argument on the attribute to override when names would collide).

Apply the correct return semantics from the table above.

### Step 4 — Copy the test template

Copy `assets/PluginTest.php.tmpl` verbatim. Substitute the same placeholders.

Adjust test cases to reflect the actual behavior being intercepted (pass-through,
argument modification, short-circuit, result enrichment).

### Step 5 — Verify placement

- The plugin class lives anywhere under `src/` — `src/Plugins/` is conventional.
- Do **not** register it in `module.php` — discovery is automatic.

### Step 6 — LSP and MCP verification gate

After writing files, expect LSP diagnostics from `marko-lsp`. Resolve all
diagnostics before declaring the plugin complete — diagnostics are the verification
gate. Then call the `find_plugins_targeting` MCP tool with the target class to
confirm the new plugin is discovered.

## Constraints

- Targeted methods must be `public` on the target class
- Target class must not be `final` (Marko avoids `final` for this reason)
- Plugin classes should be `readonly` when they have no mutable state
- Constructor property promotion always
- `declare(strict_types=1)` always
- No magic methods
- No traits

## What this skill does not cover

- Creating a new module to host the plugin — see `marko-create-module`
- Replacing an entire class — that is a Preference, not a Plugin (use
  `marko-create-preference`)
- Listening to events — that is `#[Observer]`, a different mechanism

## See also

- [Marko docs: plugins](https://marko.build/docs/concepts/plugins/)
- [Marko docs: preferences](https://marko.build/docs/concepts/preferences/)
- [`marko/core` README](https://github.com/markshust/marko/tree/develop/packages/core)
