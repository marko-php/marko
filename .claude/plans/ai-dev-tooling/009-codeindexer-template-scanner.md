# Task 009: Implement template file scanner in codeindexer

**Status**: pending
**Depends on**: 005
**Retry count**: 0

## Description
Implement a `TemplateScanner` service that walks each module's `resources/views/` directory and produces an index of templates reachable by the `module::template` naming scheme used by `marko/view`'s `ModuleTemplateResolver`.

## Context
- Namespace: `Marko\CodeIndexer\Views\TemplateScanner`
- Output type: `TemplateEntry { string $moduleName, string $templateName, string $absolutePath, string $extension }`
- Name format: `'blog::posts/index'` → module `blog`, name `posts/index`
- Extension is configurable (default `.latte` but not hardcoded) — respect module's configured extension if declared

## Requirements (Test Descriptions)
- [ ] `it discovers templates in resources/views/ across every module`
- [ ] `it produces entries keyed by module::template name`
- [ ] `it supports nested template names like posts/index`
- [ ] `it records absolute file path for each template`
- [ ] `it handles multiple template extensions when configured`
- [ ] `it returns empty when a module has no resources/views directory`

## Acceptance Criteria
- Fixtures include nested directories and multiple modules
- Scanner outputs match what `ModuleTemplateResolver` expects

## Implementation Notes
(Filled in by programmer during implementation)
