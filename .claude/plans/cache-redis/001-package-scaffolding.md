# Task 001: Package Scaffolding and Module Tests

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create the cache-redis package directory structure with composer.json, module.php, Pest.php, and module tests. Also add autoload entries to the root monorepo composer.json.

## Context
- Related files:
  - `packages/cache-array/composer.json` (template for composer.json)
  - `packages/cache-array/module.php` (template for module.php)
  - `packages/cache-array/tests/Pest.php` (template for Pest.php)
  - `packages/queue-rabbitmq/tests/ModuleTest.php` (template for module tests)
  - `composer.json` (root - add autoload entries)
- Patterns to follow:
  - Namespace: `Marko\Cache\Redis\`
  - Package name: `marko/cache-redis`
  - Type: `marko-module`
  - Dependencies: `marko/core`, `marko/config`, `marko/cache`, `predis/predis`

## Requirements (Test Descriptions)
- [x] `it binds CacheInterface to RedisCacheDriver`
- [x] `it returns valid module configuration array`
- [x] `it has marko module flag in composer.json`
- [x] `it has correct PSR-4 autoloading namespace`

## Acceptance Criteria
- All requirements have passing tests
- composer.json has correct dependencies including predis/predis ^2.0
- module.php binds CacheInterface to RedisCacheDriver
- Root composer.json has autoload entries for Marko\Cache\Redis\ and Marko\Cache\Redis\Tests\
- `predis/predis` added to root composer.json require-dev
- `composer dump-autoload` succeeds

## Implementation Notes
(Left blank - filled in by programmer during implementation)
