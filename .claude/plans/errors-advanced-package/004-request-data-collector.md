# Task 004: RequestDataCollector

**Status**: pending
**Depends on**: 002
**Retry count**: 0

## Description
Create the RequestDataCollector class to extract and format request information safely.

## Context
- Extracts request headers, method, URI
- Query parameters, POST data
- Sensitive field masking for security

## Requirements (Test Descriptions)
- [ ] `it collects request method`
- [ ] `it collects request URI`
- [ ] `it collects headers`
- [ ] `it collects query parameters`
- [ ] `it collects POST data`
- [ ] `it masks sensitive fields like password`
- [ ] `it masks authorization headers`
- [ ] `it masks API key fields`
- [ ] `it collects PHP version`
- [ ] `it collects server information`

## Acceptance Criteria
- All requirements have passing tests
- Sensitive data properly masked
- No security leaks

## Implementation Notes
(Left blank - filled in by programmer during implementation)
