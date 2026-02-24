# Task 002: Guzzle Package Scaffolding and Module Tests

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
Create the http-guzzle package scaffolding.

## Context
- Namespace: `Marko\Http\Guzzle\`
- Package: `marko/http-guzzle`
- Dependencies: marko/core, marko/http, guzzlehttp/guzzle ^7.0
- Reference: packages/cache-redis/ (driver package pattern)

## Requirements (Test Descriptions)
- [ ] `it binds HttpClientInterface to GuzzleHttpClient`
- [ ] `it returns valid module configuration array`
- [ ] `it has marko module flag in composer.json`
