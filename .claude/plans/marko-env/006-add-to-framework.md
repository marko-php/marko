# Task 006: Add marko/env to marko/framework

**Status**: pending
**Depends on**: 003
**Retry count**: 0

## Description
Add `marko/env` as an explicit dependency in the `marko/framework` metapackage. While it will be pulled in transitively via `marko/errors-simple`, adding it explicitly makes the dependency clear and documents that env support is part of the standard framework.

## Context
- Related files: `packages/framework/composer.json`
- marko/framework is a metapackage bundling common packages for web apps
- marko/errors-simple will depend on marko/env, but explicit is better than implicit
- Add after marko/core since env is foundational

## Requirements (Test Descriptions)
- [ ] `it adds marko/env to marko/framework require section`
- [ ] `it places marko/env requirement after marko/core`
- [ ] `it uses ^1.0 version constraint consistent with other packages`
- [ ] `it composer validates successfully`

## Acceptance Criteria
- marko/framework composer.json includes marko/env
- Version constraint matches other packages (^1.0)
- Composer validate passes

## Implementation Notes
(Left blank - filled in by programmer during implementation)
