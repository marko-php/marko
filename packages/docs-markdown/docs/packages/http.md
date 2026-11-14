---
title: marko/http
description: Contracts for HTTP requests — type-hint against HttpClientInterface so your code works with any HTTP driver.
---

Contracts for HTTP requests --- type-hint against `HttpClientInterface` so your code works with any HTTP driver. This package defines the `HttpClientInterface` and `HttpResponse` value object. It contains no implementation; install a driver like `marko/http-guzzle` for the actual HTTP calls. Your module code depends on the interface, making it easy to swap drivers or mock in tests.

## Installation

```bash
composer require marko/http
```

Note: You also need an implementation package such as `marko/http-guzzle`.

## Usage

### Making HTTP Requests

Inject the interface and make requests:

```php
use Marko\Http\Contracts\HttpClientInterface;

class PaymentGateway
{
    public function __construct(
        private HttpClientInterface $httpClient,
    ) {}

    public function charge(
        float $amount,
    ): array {
        $response = $this->httpClient->post('https://api.payments.com/charge', [
            'json' => ['amount' => $amount],
            'headers' => ['Authorization' => 'Bearer secret'],
        ]);

        return $response->json();
    }
}
```

### Inspecting Responses

`HttpResponse` provides status checking and body parsing:

```php
use Marko\Http\Contracts\HttpClientInterface;

$response = $this->httpClient->get('https://api.example.com/users');

if ($response->isSuccessful()) {
    $users = $response->json();
}
```

By default a 4xx or 5xx response throws `HttpException` (see [Error Responses](#error-responses)), so `isClientError()` and `isServerError()` are useful when the request opts out with `'http_errors' => false`.

### Reading Response Headers

`headers()` returns one string per header name, keyed exactly as the server sent it, with repeated values joined by `", "`. That join is lossy for `Set-Cookie`, whose values contain commas of their own (`Expires=Wed, 21 Oct 2026 07:28:00 GMT`). Read headers through `header()` and `headerValues()` instead. Both match the name case-insensitively:

```php
$response = $client->post('https://sso.example.com/handoff');

$response->header('content-type');        // 'application/json', or null when absent
$response->headerValues('set-cookie');    // ['session=abc; Expires=Wed, 21 Oct 2026 07:28:00 GMT', 'theme=dark']
$response->headerValues('x-missing');     // []
```

`headerValues()` returns every value in the order the server sent them. A driver passes them as `headerValues`. When a response is built without `headerValues` (for example by a driver written before it existed), each `headers` entry becomes a one-element list, which is lossless for headers that appear once.

When a response is built with only `headerValues`, `headers()` returns those values joined with `", "`. When `headers` is passed, `headers()` returns it unchanged.

### Request Options

All methods accept an `$options` array. The keys are a portable set defined as constants on `Marko\Http\RequestOptions`, so the same call works with any driver:

| Option | Constant | Value |
|---|---|---|
| `headers` | `RequestOptions::HEADERS` | `array<string, string\|string[]>` of request headers |
| `body` | `RequestOptions::BODY` | Raw request body (string) |
| `json` | `RequestOptions::JSON` | Data sent as a JSON body |
| `form_params` | `RequestOptions::FORM_PARAMS` | `array` sent as an `application/x-www-form-urlencoded` body |
| `multipart` | `RequestOptions::MULTIPART` | List of `['name' => ..., 'contents' => ..., 'filename' => ...]` parts sent as `multipart/form-data` |
| `query` | `RequestOptions::QUERY` | Query string parameters |
| `timeout` | `RequestOptions::TIMEOUT` | Total request timeout in seconds |
| `connect_timeout` | `RequestOptions::CONNECT_TIMEOUT` | Connection timeout in seconds |
| `auth` | `RequestOptions::AUTH` | `['username', 'password']` for basic auth, or `['bearer' => $token]` |
| `verify` | `RequestOptions::VERIFY` | `bool` to toggle TLS verification, or a path to a CA bundle |
| `allow_redirects` | `RequestOptions::ALLOW_REDIRECTS` | `bool`, or the maximum number of redirects as an `int` |
| `proxy` | `RequestOptions::PROXY` | Proxy URL |
| `http_errors` | `RequestOptions::HTTP_ERRORS` | `bool`, default `true` --- throw `HttpException` on 4xx/5xx |
| `resolve_to` | `RequestOptions::RESOLVE_TO` | IPv4 or IPv6 address (no brackets) to connect to instead of resolving the host --- see [Pinning the Connection to an IP](#pinning-the-connection-to-an-ip) |

```php
use Marko\Http\RequestOptions;

$response = $this->httpClient->post('https://api.example.com/token', [
    RequestOptions::FORM_PARAMS => ['grant_type' => 'client_credentials'],
    RequestOptions::AUTH => [$clientId, $clientSecret],
    RequestOptions::CONNECT_TIMEOUT => 2,
]);
```

Options are validated before the request is sent. Each of these throws `InvalidRequestOptionException`:

- An unknown key, such as the typo `form_param` --- the message names the key and the suggestion lists every supported key.
- More than one body option (`body`, `json`, `form_params`, `multipart`) in the same request.
- A malformed `auth`, or a `http_errors`, `verify`, `allow_redirects` or `resolve_to` value of the wrong type.
- `resolve_to` combined with `proxy`, or without `'allow_redirects' => false`.

Drivers may accept an extra, clearly non-portable key of their own (for example `guzzle` in [`marko/http-guzzle`](/docs/packages/http-guzzle/)). Any other key fails loudly instead of being ignored.

### Pinning the Connection to an IP

When you have already resolved and vetted a host --- for example to reject internal addresses before calling a user-supplied URL --- pass the address you checked as `resolve_to`. The client connects to that address instead of resolving the host again, while the `Host` header, TLS SNI and certificate verification still use the hostname from the URL:

```php
use Marko\Http\RequestOptions;

$response = $this->httpClient->post('https://hooks.example.com/in', [
    RequestOptions::JSON => $event,
    RequestOptions::RESOLVE_TO => '93.184.215.14',
    RequestOptions::ALLOW_REDIRECTS => false,
]);
```

Without the pin, a hostname whose DNS answer changes between your check and the connection (DNS rebinding) can steer the request to an address you never approved. [`marko/webhook`](/docs/packages/webhook/#outgoing-url-policy) uses this option for every outgoing webhook.

The pin covers only the URL's host and port, so:

- `resolve_to` requires `'allow_redirects' => false` --- a redirect to another host would be resolved normally. Check and pin a redirect target yourself before following it.
- `resolve_to` cannot be combined with `proxy`, because a proxy resolves the destination itself.

A driver that cannot pin the connection throws `InvalidRequestOptionException` instead of ignoring the option, so the request is never silently sent unpinned.

### Error Responses

With the default `'http_errors' => true`, a 4xx or 5xx response throws `HttpException`, and the response is available from `getResponse()`:

```php
use Marko\Http\Exceptions\HttpException;

try {
    $this->httpClient->get('https://api.example.com/orders/42');
} catch (HttpException $e) {
    $status = $e->getResponse()?->statusCode();
}
```

For APIs that use status codes as normal flow (404 not found, 409 conflict, 422 validation errors), pass `'http_errors' => false` to get the `HttpResponse` back instead:

```php
$response = $this->httpClient->get('https://api.example.com/orders/42', [
    'http_errors' => false,
]);

if ($response->statusCode() === 404) {
    return null;
}

if ($response->isServerError()) {
    throw new OrderServiceUnavailable();
}
```

Connection failures always throw `ConnectionException`, whatever the value of `http_errors`.

When you report an error response, use `bodyExcerpt()` instead of `body()`. It trims the body and caps it at 500 bytes (or the limit you pass), cutting on a UTF-8 character boundary and appending `... [truncated N bytes]`, so a large HTML error page doesn't flood your logs:

```php
if (!$response->isSuccessful()) {
    throw new OrderSyncFailed("Orders API responded with HTTP {$response->statusCode()}: {$response->bodyExcerpt()}");
}
```

### Testing

Use `FakeHttpClient` from [`marko/testing`](/docs/packages/testing/#fakehttpclient) to stub responses and assert on sent requests without a live server. It applies the same option validation and `http_errors` behaviour as a real driver.

## API Reference

### HttpClientInterface

All methods throw `InvalidRequestOptionException` for invalid options, `ConnectionException` on network failure, and `HttpException` on a 4xx/5xx response unless `http_errors` is `false`.

```php
use Marko\Http\Contracts\HttpClientInterface;
use Marko\Http\HttpResponse;

public function request(
    string $method,
    string $url,
    array $options = [],
): HttpResponse;
public function get(
    string $url,
    array $options = [],
): HttpResponse;
public function post(
    string $url,
    array $options = [],
): HttpResponse;
public function put(
    string $url,
    array $options = [],
): HttpResponse;
public function patch(
    string $url,
    array $options = [],
): HttpResponse;
public function delete(
    string $url,
    array $options = [],
): HttpResponse;
```

### HttpResponse

A `readonly` value object constructed with a status code, body, headers, and (optionally) every value of each header.

```php
use Marko\Http\HttpResponse;

public function __construct(
    int $statusCode,
    string $body,
    array $headers = [],       // array<string, string>: one string per header name
    array $headerValues = [],  // array<string, list<string>>: every value, in order
);

public function statusCode(): int;
public function body(): string;
public function headers(): array;                     // array<string, string>, exact-case keys; joined from headerValues when headers is empty
public function header(string $name): ?string;        // values joined with ", ", or null when absent
public function headerValues(string $name): array;    // list<string>, or [] when absent
public function bodyExcerpt(int $maxBytes = 500): string; // trimmed body capped at $maxBytes, "... [truncated N bytes]" appended when cut
public function json(): mixed;          // throws JsonException on invalid JSON
public function isSuccessful(): bool;   // 2xx
public function isRedirect(): bool;     // 3xx
public function isClientError(): bool;  // 4xx
public function isServerError(): bool;  // 5xx
```

### RequestOptions

```php
use Marko\Http\RequestOptions;

RequestOptions::SUPPORTED;                                   // every portable option key
RequestOptions::validate(array $options, array $driverKeys = []): void;  // throws InvalidRequestOptionException
RequestOptions::throwsOnHttpError(array $options): bool;     // false only when 'http_errors' => false
RequestOptions::bearerToken(array $options): ?string;        // token from ['bearer' => ...] auth, else null
```

Drivers call `validate()` before sending, passing any driver-specific keys they accept, so every driver and `FakeHttpClient` share one rule set.

### Exceptions

| Exception | Description |
|-----------|-------------|
| `InvalidRequestOptionException` | Thrown before sending when an option key is unknown, options conflict, an option value has the wrong shape, or the driver cannot honour an option such as `resolve_to` (extends `MarkoException`) |
| `HttpException` | Base exception for HTTP errors --- provides `getResponse()` to access the underlying `HttpResponse` if available |
| `ConnectionException` | Thrown when the HTTP connection itself fails (extends `HttpException`) |
