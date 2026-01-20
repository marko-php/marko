# Project Overview

## Project Name
Marko Framework

## Tagline
> **Opinionated, not restrictive. There's always a way - it's just the right way.**

## Description
Marko is a PHP framework that combines enterprise-grade extensibility (inspired by Magento) with modern developer experience (inspired by Laravel). It provides true modularity, dependency injection with preferences and plugins, event-driven architecture, and service contracts - all with strong opinions that guide developers toward better architecture.

## Tech Stack
- **Language**: PHP 8.5+
- **Testing**: Pest PHP
- **Linting**: PHP_CodeSniffer (phpcs) + PHP CS Fixer
- **Package Manager**: Composer
- **Autoloading**: PSR-4

## PHP 8.5 Features to Leverage
- **Pipe Operator (`|>`)**: For clean functional composition in services
- **Clone With**: For readonly DTOs and value objects
- **#[\NoDiscard] Attribute**: Aligns with "loud errors" philosophy - warn when return values are ignored
- **Closures in Constant Expressions**: Enables closures in attributes for complex configurations
- **array_first() / array_last()**: Cleaner array operations
- **Attributes on Constants**: More flexible metadata

**Note on `final`**: Avoid `final` classes/properties as they block extensibility via Preferences. Use `readonly` for immutability instead.

## Project Type
PHP Framework (monorepo containing multiple packages)

## Core Principles

### 1. Opinionated, Not Restrictive
- Makes the right thing easy and the wrong thing annoying (not impossible)
- Every "no" comes with a "yes, this way instead"
- Guardrails guide, they don't wall

### 2. Loud Errors
- No silent failures or ambiguous behavior
- Every decision is explicit
- Every conflict is surfaced
- Every mistake is caught early with helpful messages

### 3. Explicit Over Implicit
- Explicit dependencies via constructor injection
- Explicit overrides via Preferences
- Explicit configuration via PHP files
- No magic methods, no XML, no DSL

### 4. True Modularity
- Everything is a module (framework, vendor, application code)
- Same rules apply everywhere
- Interface/implementation split pattern
- Clean package boundaries

## Target Developers
- Developers who value architectural consistency
- Team leads who desire predictable codebases
- Agencies maintaining multiple client projects
- Senior devs enforcing best practices
- Anyone building modular applications that last

## Official Resources
| Resource | Location |
|----------|----------|
| Website | marko.build |
| GitHub Organization | github.com/devtomic |
| Monorepo | github.com/devtomic/marko |
| Packagist | packagist.org/packages/marko |
