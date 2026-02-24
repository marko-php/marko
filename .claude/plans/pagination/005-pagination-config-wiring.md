# Task 005: PaginationConfig, module.php, composer.json Wiring

**Status**: done
**Depends on**: 001
**Retry count**: 0

## Description
Create `PaginationConfig` for loading default pagination settings from config, the `module.php` for container registration, the `composer.json` for package metadata, and the default config file. PaginationConfig provides default per-page and max per-page values that can be used by controllers when creating paginators.

## Context
- Class: `Marko\Pagination\Config\PaginationConfig`
- Config file: `config/pagination.php` with per_page and max_per_page defaults
- PaginationConfig reads from ConfigRepositoryInterface
- module.php registers bindings (PaginationConfig only -- paginators are instantiated directly, not resolved from container)
- composer.json: name marko/pagination, requires marko/core and marko/config, PSR-4 autoload Marko\Pagination\
- No hardcoded version in composer.json
- PaginationConfig validates that per_page does not exceed max_per_page
- Provides a clampPerPage(int) method that enforces the max_per_page limit

## Requirements (Test Descriptions)
- [ ] `it loads per_page and max_per_page from config`
- [ ] `it clamps requested perPage to max_per_page`
- [ ] `it throws PaginationException when per_page exceeds max_per_page in config`
- [ ] `it has valid composer.json with marko module type and PSR-4 autoload`
- [ ] `it registers PaginationConfig binding in module.php`
- [ ] `it provides default config file with per_page and max_per_page`

## Acceptance Criteria
- PaginationConfig has perPage and maxPerPage readonly properties
- PaginationConfig loads values from ConfigRepositoryInterface using 'pagination.per_page' and 'pagination.max_per_page' keys
- clampPerPage(int $requested) returns min($requested, $maxPerPage), ensuring per-page never exceeds the configured max
- Default config file defines per_page = 15, max_per_page = 100
- composer.json has name "marko/pagination", type "marko-module", requires marko/core and marko/config
- composer.json has no hardcoded "version" field
- module.php returns array with enabled = true and bindings for PaginationConfig
- All files have declare(strict_types=1) and proper namespacing

## Implementation Notes
(Left blank)
