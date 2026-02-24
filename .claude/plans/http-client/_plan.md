# Plan: http-client Packages

## Created
2026-02-23

## Status
completed

## Objective
Build `marko/http` (interface) and `marko/http-guzzle` (driver) — HTTP client for making outgoing requests.

## Scope
### In Scope
- HttpClientInterface with get/post/put/patch/delete/request methods
- HttpResponse value object (statusCode, body, headers)
- Guzzle-based implementation
- Exception hierarchy for HTTP errors

### Out of Scope
- Async/concurrent requests
- Middleware pipeline for outgoing requests
- OAuth/auth helpers

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Interface package (marko/http) | - | completed |
| 002 | Guzzle package scaffolding and module tests | - | completed |
| 003 | GuzzleHttpClient implementation and tests | 001, 002 | completed |

## Architecture Notes
- HttpClientInterface: request(method, url, options): HttpResponse
- Convenience methods: get, post, put, patch, delete
- HttpResponse: readonly with statusCode, body, headers, json(), isSuccessful()
- Options array: headers, body, json, query, timeout
- HttpException contains the response for inspection
