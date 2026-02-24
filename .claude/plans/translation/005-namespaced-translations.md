# Task 005: Namespaced Translations (blog::messages.key)

**Status**: done
**Depends on**: 004
**Retry count**: 0

## Description
Extend FileTranslationLoader to support namespaced translations. Modules can register their own lang directories under a namespace, enabling keys like `blog::messages.welcome` to load from the blog module's `lang/` directory instead of the application's default. This allows each Marko module to ship its own translations without conflicting with other modules.

## Context
- Namespace syntax: `{namespace}::{group}.{key}` (e.g., `blog::messages.welcome`)
- The `::` separator splits namespace from the rest; the Translator parses this before calling the loader
- FileTranslationLoader maintains a registry of namespace -> path mappings via `addNamespace()`
- `addNamespace('blog', '/path/to/packages/blog/lang')` registers the blog namespace
- `load('en', 'messages', 'blog')` reads from `/path/to/packages/blog/lang/en/messages.php`
- Without namespace (null), loads from the default base path as before
- Namespaced translations are cached independently from default translations
- Reference: Laravel's `$translator->addNamespace()` pattern, but explicit and non-magic

## Requirements (Test Descriptions)
- [ ] `it registers a namespace with addNamespace and loads from that path`
- [ ] `it loads namespaced translations from {namespacePath}/lang/{locale}/{group}.php`
- [ ] `it returns empty array when namespaced translation file does not exist`
- [ ] `it caches namespaced translations independently from default translations`
- [ ] `it supports multiple namespaces simultaneously`
- [ ] `it throws TranslationException when loading from unregistered namespace`

## Acceptance Criteria
- `addNamespace(string $namespace, string $path): void` method on FileTranslationLoader
- After `addNamespace('blog', '/path/to/blog/lang')`, calling `load('en', 'messages', 'blog')` reads `/path/to/blog/lang/en/messages.php`
- Calling `load('en', 'messages', null)` still reads from the default base path
- Cache key includes namespace: `blog::en.messages` vs `en.messages`
- Multiple namespaces can be registered: `blog`, `admin`, `shop`, etc.
- Loading from an unregistered namespace throws TranslationException with a helpful suggestion to register it
- The Translator class (from task 002) parses `blog::messages.welcome` by splitting on `::` and passes namespace to the loader
- Tests use temporary directories with real PHP files for multiple namespaces
- Uses `declare(strict_types=1)`, no `final`

## Implementation Notes
(Left blank)
