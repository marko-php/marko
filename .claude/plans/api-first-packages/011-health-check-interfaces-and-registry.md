# Task 011: Health Check Interfaces and Registry

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
Create the marko/health package scaffolding with the HealthCheckInterface, HealthResult value object, HealthStatus enum, and HealthCheckRegistry. This establishes the contract for application health monitoring.

## Context
- New package at `packages/health/`
- Namespace: `Marko\Health`
- Depends on: marko/core, marko/routing, marko/config
- Soft dependencies (suggest): marko/database, marko/cache, marko/queue, marko/filesystem
- Study `packages/cache/` for interface package scaffolding pattern
- Study `packages/validation/src/Validation/ValidationErrors.php` for value object collection pattern
- HealthCheckRegistry collects all registered checks and runs them
- HealthStatus enum: healthy, degraded, unhealthy
- HealthResult: name, status, message, metadata array, duration (milliseconds)

## Requirements (Test Descriptions)
- [ ] `it defines HealthCheckInterface with name and check methods returning HealthResult`
- [ ] `it defines HealthStatus enum with healthy, degraded, and unhealthy cases`
- [ ] `it defines HealthResult value object with status, name, message, metadata, and duration`
- [ ] `it registers health checks in HealthCheckRegistry and retrieves them`
- [ ] `it creates valid package scaffolding with composer.json, module.php, and config`

## Acceptance Criteria
- All requirements have passing tests
- Interfaces in `src/Contracts/`
- Value objects in `src/Value/`
- Registry in `src/Registry/HealthCheckRegistry.php`
- Exceptions in `src/Exceptions/`
- Config at `config/health.php` with route path, enabled checks
- Code follows code standards

## Implementation Notes
