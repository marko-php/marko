# Task 014: errors Package README

**Status**: complete
**Depends on**: 005
**Retry count**: 0

## Description
Create the README.md documentation for the `marko/errors` package. This README explains what the package provides, why it exists as a separate interface package, and how it fits into the error handling architecture.

## Context
- Related files: `packages/errors/README.md`
- Tone: Conversational, explains the "why" not just the "what"
- NO code examples - the code is the specification
- Should help developers understand when/why to use this package

## Requirements (Test Descriptions)
- [x] `it has a clear title and one-line description`
- [x] `it explains the purpose of the interface package`
- [x] `it explains the interface/implementation split pattern`
- [x] `it describes ErrorHandlerInterface and its role`
- [x] `it describes ErrorReporterInterface and when you'd use it`
- [x] `it describes ErrorReport as the standardized error container`
- [x] `it describes Severity enum and its purpose`
- [x] `it explains the relationship to marko/core exceptions`
- [x] `it mentions available implementations (errors-simple, errors-advanced future)`
- [x] `it explains that this package has no implementation - just contracts`
- [x] `it explains how to create a custom error handler implementation`
- [x] `it explains how to create a custom error reporter for external services`
- [x] `it explains how to type-hint against the interfaces in application code`
- [x] `it follows conversational tone without being verbose`
- [x] `it may include public API signatures (interfaces, method signatures) for documentation`
- [x] `it avoids implementation details or internal code examples`

## Acceptance Criteria
- README exists and is well-structured
- Explains concepts without implementation details
- Helps developers understand the architecture
- Conversational but professional tone
