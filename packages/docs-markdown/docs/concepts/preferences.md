---
title: Preferences
description: Replace any concrete class globally without modifying vendor code.
---

Preferences are Marko's mechanism for replacing one concrete class with another globally. They're the clean way to say "whenever the system creates X, give it my version instead."

This is distinct from [bindings](/docs/concepts/dependency-injection/), which map interfaces to implementations. Preferences swap **class for class**.

## How Preferences Work

Use the `#[Preference]` attribute on your replacement class to declare what it replaces:

```php title="app/blog/src/Controller/CustomPostController.php"
<?php

declare(strict_types=1);

namespace App\Blog\Controller;

use Acme\Blog\Controller\PostController;
use Marko\Core\Attributes\Preference;

#[Preference(replaces: PostController::class)]
class CustomPostController extends PostController
{
    // Override specific methods as needed
}
```

Now everywhere the system would create `PostController`, it creates `CustomPostController` instead. No configuration files, no binding overrides — the attribute declares the intent right on the class.

## When to Use Preferences

Preferences are the right tool when you want to **replace a concrete class**:

- Replace a vendor controller with your own
- Swap a service class for a custom implementation
- Override a model with extended behavior

## When to Use Bindings Instead

If you're swapping which implementation an **interface** resolves to, use a binding in `module.php`:

```php title="app/my-app/module.php"
<?php

use Marko\Cache\Contracts\CacheInterface;
use Marko\Cache\Redis\Driver\RedisCacheDriver;

return [
    'bindings' => [
        CacheInterface::class => RedisCacheDriver::class,
    ],
];
```

## Preferences vs Plugins vs Bindings

| Use Case | Tool |
|---|---|
| Replace a concrete class globally | **Preference** (`#[Preference]` attribute) |
| Swap an interface's implementation | **Binding** (`module.php` bindings) |
| Modify input/output of a specific method | **[Plugin](/docs/concepts/plugins/)** |
| React to something happening | **[Observer](/docs/concepts/events/)** |

Preferences and bindings are coarse-grained (whole class replacement). Plugins are fine-grained (method-level interception). Use the right tool for the scope of your change.

## Arguments Are Inherited

When a Preference replaces a class, the replacement is built with the replaced class's [`arguments`](/docs/concepts/dependency-injection/#argument-substitution). The replacement's own `arguments` override them per argument. Every parameter name the replaced class's arguments set must exist on the replacement's constructor, or boot fails with an `ArgumentException`.

This applies only to Preferences. A subclass that merely `extends` a class, with no `#[Preference]`, inherits none of its parent's arguments.

## Preference Chains

Preferences can chain. If module A prefers `PostController` → `CustomPostController`, and module B prefers `CustomPostController` → `AdvancedPostController`, the container follows the chain to the final replacement.

## Conflict Handling

Marko enforces explicit, deterministic Preference resolution — no silent "last one wins" behavior.

**Same-priority conflict:** If two modules at the same priority level (e.g., two `vendor/` packages) both define a Preference for the same class, Marko throws a `PreferenceConflictException` naming both modules. You must resolve the conflict by moving one Preference to a higher-priority module or removing the duplicate.

**Different priorities:** Higher-priority modules override lower ones silently, as designed. `app/` beats `modules/` beats `vendor/`. This is the intended mechanism for customization.

This matches how [binding conflicts](/docs/concepts/dependency-injection/) work — same rules, same loud errors.

## Rules

1. **Preferences replace concrete classes**, not interfaces. For interface swapping, use bindings.
2. **Higher-priority modules win.** `app/` beats `modules/` beats `vendor/`.
3. **Same-priority conflicts are errors.** Two vendor packages can't both prefer the same class.

## Next Steps

- [Plugins](/docs/concepts/plugins/) — for method-level modifications
- [Events & Observers](/docs/concepts/events/) — for reactive behavior
- [Dependency Injection](/docs/concepts/dependency-injection/) — how bindings and the container work
- [Core Package](/docs/packages/core/) — API reference for Preferences
