# Task 003: GuzzleHttpClient Implementation and Tests

**Status**: pending
**Depends on**: 001, 002
**Retry count**: 0

## Description
Implement GuzzleHttpClient wrapping Guzzle's Client. Maps Guzzle responses to Marko HttpResponse objects. Handles exceptions by wrapping them in Marko exception types.

## Context
- Reference: packages/cache-redis/src/RedisConnection.php (lazy client pattern)
- Protected createClient() method for test mocking
- Map GuzzleHttp\Exception\ConnectException → ConnectionException
- Map GuzzleHttp\Exception\RequestException → HttpException

## Requirements (Test Descriptions)
- [ ] `it implements HttpClientInterface`
- [ ] `it sends GET request and returns response`
- [ ] `it sends POST request with json body`
- [ ] `it sends PUT request with body`
- [ ] `it sends DELETE request`
- [ ] `it includes custom headers in request`
- [ ] `it maps response status code`
- [ ] `it maps response headers`
- [ ] `it throws HttpException for error status codes`
- [ ] `it throws ConnectionException for network failures`
