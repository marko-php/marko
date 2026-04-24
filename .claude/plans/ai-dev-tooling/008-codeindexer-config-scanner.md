# Task 008: Implement config file scanner in codeindexer

**Status**: pending
**Depends on**: 005
**Retry count**: 0

## Description
Implement a `ConfigScanner` service that finds all `config/*.php` files across modules, evaluates them safely (they return arrays per Marko convention), and extracts the full dot-notation key tree. Powers config-key completion and diagnostics in the LSP.

## Context
- Namespace: `Marko\CodeIndexer\Config\ConfigScanner`
- Output type: `ConfigKeyEntry { string $key, mixed $defaultValue, string $file, int $line, string $type }`
- Dot-notation flattening: `['mail' => ['driver' => 'smtp']]` → `mail.driver = 'smtp'`
- Must handle scoped config (`default` + `scopes.{tenant}` pattern per architecture.md)
- IMPORTANT: Does NOT execute config files. Uses `nikic/php-parser` AST to read the returned array literal. Executing arbitrary vendor config files at index time risks side effects, missing constants/env, and runtime dependencies on the host app. Any config file whose return value is not a static array literal is recorded as a diagnostic with "dynamic config — value unavailable" and still has its top-level keys indexed when possible.

## Requirements (Test Descriptions)
- [ ] `it discovers config files in every module config directory`
- [ ] `it flattens nested arrays to dot-notation keys`
- [ ] `it captures scalar default values with their types`
- [ ] `it records source file and line number for each top-level key`
- [ ] `it handles scoped config with default plus scopes cascade`
- [ ] `it ignores config files with syntax errors and records them as warnings`
- [ ] `it returns entries namespaced by the module name`
- [ ] `it does not include or eval config files — reads values via AST only`
- [ ] `it records dynamic config files (non-static return) as diagnostics while still indexing known keys`

## Acceptance Criteria
- Fixtures include multi-level nested config and scoped config
- Line numbers are accurate for top-level keys
- No state leaks between config file evaluations

## Implementation Notes
(Filled in by programmer during implementation)
