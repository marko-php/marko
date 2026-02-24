# Task 001: API Package Interfaces and Exceptions

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create the marko/api package scaffolding with core interfaces, value objects, and exceptions. This establishes the contract for transforming entities into structured JSON API responses.

## Context
- New package at `packages/api/`
- Follow existing patterns from `packages/cache/` (interface package structure)
- Depends on: marko/core, marko/routing (for Response), marko/pagination
- Namespace: `Marko\Api`
- Study `packages/cache/composer.json`, `packages/cache/module.php` for scaffolding patterns
- Study `packages/cache/src/Exceptions/` for exception patterns (message + context + suggestion)
- Study `packages/admin-api/src/Response/ApiResponse.php` for existing JSON response patterns

## Requirements (Test Descriptions)
- [x] `it defines ResourceInterface with toArray and toResponse methods`
- [x] `it defines ResourceCollectionInterface with toArray, toResponse, and withPagination methods`
- [x] `it provides ConditionalValue class that wraps a value with a boolean condition`
- [x] `it provides MissingValue sentinel class for marking fields to be omitted`
- [x] `it throws ApiResourceException with context and suggestion for invalid resource data`
- [x] `it creates valid package scaffolding with composer.json, module.php, and config`

## Acceptance Criteria
- All requirements have passing tests
- composer.json has correct dependencies (marko/core, marko/routing, marko/pagination)
- module.php exists with empty bindings (implementations come in later tasks)
- Interfaces are in `src/Contracts/`
- Exceptions are in `src/Exceptions/`
- Value objects are in `src/Value/`
- Code follows code standards (strict types, constructor promotion, no final)

## Implementation Notes
- Created `packages/api/` with full structure: src/Contracts/, src/Exceptions/, src/Value/, config/, tests/
- ResourceInterface: toArray(): array, toResponse(): Response
- ResourceCollectionInterface: toArray(): array, toResponse(): Response, withPagination(PaginatorInterface): static
- ConditionalValue: wraps value + bool; resolve() returns value or MissingValue based on condition
- MissingValue: sentinel class with no properties, used as a flag for fields to omit
- ApiResourceException: extends Exception with message, context, suggestion (matches CacheException pattern)
- module.php: empty bindings array (implementations come in later tasks)
- config/api.php: returns empty array
- Added Marko\Api namespace to root composer.json autoload
- 31 tests total, all passing
