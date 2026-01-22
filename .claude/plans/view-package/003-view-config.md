# Task 003: ViewConfig Value Object

**Status**: complete
**Depends on**: 001
**Retry count**: 0

## Description
Create the ViewConfig value object for view configuration.

## Context
- cacheDirectory - where compiled templates are stored
- extension - template file extension (default .latte)
- autoRefresh - whether to check for template changes (dev only)
- strictTypes - whether templates use strict types
- Loads from ConfigRepositoryInterface

## Requirements (Test Descriptions)
- [x] `ViewConfig has cache directory property`
- [x] `ViewConfig has extension with default`
- [x] `ViewConfig has auto refresh with default`
- [x] `ViewConfig loads from config repository`

## Implementation Notes
Created ViewConfig readonly class at `/Users/markshust/Sites/marko/packages/view/src/ViewConfig.php` following the same pattern as QueueConfig. The class:
- Takes ConfigRepositoryInterface via constructor injection
- Provides typed getter methods for each config property
- Uses sensible defaults: /tmp/views for cache, .latte extension, true for autoRefresh and strictTypes
- All configuration loaded from view.* namespace in config repository
