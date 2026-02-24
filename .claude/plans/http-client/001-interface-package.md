# Task 001: Interface Package (marko/http)

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
Create the HTTP client interface package with HttpClientInterface, HttpResponse, and exceptions.

## Context
- Namespace: `Marko\Http\`
- Package: `marko/http`
- Dependencies: marko/core
- NOTE: This is for OUTGOING HTTP requests, not incoming. The routing package handles incoming requests.

## Requirements (Test Descriptions)
- [ ] `it defines HttpClientInterface with request method`
- [ ] `it defines HttpClientInterface with convenience methods`
- [ ] `it creates HttpResponse with status code body and headers`
- [ ] `it returns json decoded body from HttpResponse`
- [ ] `it reports successful status codes from HttpResponse`
- [ ] `it defines HttpException with response access`
- [ ] `it defines ConnectionException for network failures`
- [ ] `it has marko module flag in composer.json`
