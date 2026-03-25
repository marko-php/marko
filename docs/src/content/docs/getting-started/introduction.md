---
title: Introduction
description: What is Marko and why should you use it?
---

Marko is a modular PHP 8.5+ framework that combines Magento's powerful extensibility system with Laravel's developer experience. It's built on four core principles:

1. **Pragmatically opinionated** — The right thing is easy, the wrong thing is annoying. Every "no" comes with a "yes, this way instead."
2. **True modularity** — Interface and implementation are split. Clean boundaries between packages. Everything is a module.
3. **Explicit over implicit** — No magic methods, no hidden conventions. Everything is discoverable and type-safe.
4. **Loud errors** — No silent failures. Every error tells you what went wrong, gives you context, and suggests a fix.

## The Core Equation

```
Magento's extensibility + Laravel's DX + Loud errors = Marko
```

From **Magento**, Marko takes the extensibility primitives: Preferences (interface swapping), Plugins (method interception), Events & Observers, and a layered module system.

From **Laravel**, Marko takes the developer experience: clean APIs, sensible defaults, Composer-native package management, and attribute-based routing.

From **neither**, Marko adds loud errors — a philosophy that every failure should help you fix it.

## How Modules Work

Everything in Marko is a module. A module is any Composer package with `"extra": { "marko": { "module": true } }` in its `composer.json`.

Modules live in three locations, resolved in priority order:

| Location | Purpose | Priority |
|---|---|---|
| `app/` | Your application customizations | Highest |
| `modules/` | Manually installed third-party modules | Medium |
| `vendor/` | Composer-installed packages | Lowest |

Higher-priority modules can override lower ones using Preferences, Plugins, and configuration merging.

## Package Architecture

Marko splits features into **interface packages** and **implementation packages**:

```
marko/cache          → CacheInterface, CacheItemInterface (contracts)
marko/cache-file     → FileCacheDriver (file-based implementation)
marko/cache-redis    → RedisCacheDriver (Redis implementation)
```

You code against the interface. Swap implementations by changing a single binding — no other code changes needed.

## What's Next?

- [Install Marko](/docs/getting-started/installation/) and create your first project
- [Understand the project structure](/docs/getting-started/project-structure/)
- [Learn about modularity](/docs/concepts/modularity/) — the foundation of everything in Marko
