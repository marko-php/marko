# Task 009: PrettyHtmlFormatter Request/Environment Display

**Status**: completed
**Depends on**: 005, 004
**Retry count**: 0

## Description
Display request and environment information in error pages.

## Context
- Uses RequestDataCollector for data
- Displays headers, params, server info
- Sensitive data already masked by collector

## Requirements (Test Descriptions)
- [x] `it displays request method and URI`
- [x] `it displays request headers`
- [x] `it displays query parameters`
- [x] `it displays POST data`
- [x] `it displays PHP version`
- [x] `it displays server information`
- [x] `it formats data in readable sections`

## Acceptance Criteria
- All requirements have passing tests
- Information is organized and readable
- Sensitive data is masked

## Implementation Notes
- Added `RequestDataCollector` parameter to `PrettyHtmlFormatter` constructor with default instantiation
- Created `formatRequestData()` method that collects and displays request method, URI, headers, query params, and POST data
- Created `formatKeyValueTable()` helper method to render data in HTML tables with proper escaping
- Created `formatServerInfo()` method to display PHP version, server software, and server name
- Request data is displayed in a dedicated `request-info` section with subsections for headers, query params, and POST data
- Server/environment info is displayed in a separate `environment-info` section
- All data is properly HTML escaped to prevent XSS
- Empty sections are automatically hidden (e.g., if no headers are present)
- Created `createTestRequestCollector()` test helper function using anonymous class extension for mocking
