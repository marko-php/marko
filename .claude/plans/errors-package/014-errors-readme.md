# Task 014: errors Package README

**Status**: pending
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
- [ ] `it has a clear title and one-line description`
- [ ] `it explains the purpose of the interface package`
- [ ] `it explains the interface/implementation split pattern`
- [ ] `it describes ErrorHandlerInterface and its role`
- [ ] `it describes ErrorReporterInterface and when you'd use it`
- [ ] `it describes ErrorReport as the standardized error container`
- [ ] `it describes Severity enum and its purpose`
- [ ] `it explains the relationship to marko/core exceptions`
- [ ] `it mentions available implementations (errors-simple, errors-advanced future)`
- [ ] `it explains that this package has no implementation - just contracts`
- [ ] `it explains how to create a custom error handler implementation`
- [ ] `it explains how to create a custom error reporter for external services`
- [ ] `it explains how to type-hint against the interfaces in application code`
- [ ] `it follows conversational tone without being verbose`
- [ ] `it may include public API signatures (interfaces, method signatures) for documentation`
- [ ] `it avoids implementation details or internal code examples`

## Acceptance Criteria
- README exists and is well-structured
- Explains concepts without implementation details
- Helps developers understand the architecture
- Conversational but professional tone
