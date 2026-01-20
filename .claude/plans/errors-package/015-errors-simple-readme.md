# Task 015: errors-simple Package README

**Status**: completed
**Depends on**: 012
**Retry count**: 0

## Description
Create the README.md documentation for the `marko/errors-simple` package. This README explains what the simple error handler provides, why it's the "reliable fallback", and when you'd choose it over alternatives.

## Context
- Related files: `packages/errors-simple/README.md`
- Tone: Conversational, explains the "why" not just the "what"
- NO code examples - the code is the specification
- Should help developers understand the design philosophy

## Requirements (Test Descriptions)
- [x] `it has a clear title and one-line description`
- [x] `it explains this is the reliable fallback implementation`
- [x] `it explains the zero-dependency philosophy`
- [x] `it describes CLI output with colored stack traces`
- [x] `it describes web output with basic HTML error pages`
- [x] `it explains development vs production mode behavior`
- [x] `it explains environment detection approach`
- [x] `it explains the fallback chain concept`
- [x] `it mentions this catches errors in fancier error handlers`
- [x] `it explains when to use simple vs advanced (when available)`
- [x] `it describes automatic registration via module boot`
- [x] `it explains how to configure environment variables for dev/prod mode`
- [x] `it explains how to use this as a fallback in custom error handlers`
- [x] `it explains how to extend or customize the formatters if needed`
- [x] `it follows conversational tone without being verbose`
- [x] `it may include public API signatures and configuration examples`
- [x] `it avoids implementation details or internal code examples`

## Acceptance Criteria
- README exists and is well-structured
- Explains the "reliable fallback" philosophy clearly
- Helps developers understand when to use this package
- Conversational but professional tone
