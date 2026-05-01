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

if ($response->isClientError()) {
    // Handle 4xx
}
```

### Request Options

All methods accept an `$options` array supporting:

- `headers` --- Request headers
- `body` --- Raw request body
- `json` --- JSON-encoded body
- `query` --- Query string parameters
- `timeout` --- Request timeout in seconds

## API Reference

### HttpClientInterface

All methods throw `HttpException` or `ConnectionException` on failure.

```php
use Marko\Http\Contracts\HttpClientInterface;
use Marko\Http\HttpResponse;

public function request(string $method, string $url, array $options = []): HttpResponse;
public function get(string $url, array $options = []): HttpResponse;
public function post(string $url, array $options = []): HttpResponse;
public function put(string $url, array $options = []): HttpResponse;
public function patch(string $url, array $options = []): HttpResponse;
public function delete(string $url, array $options = []): HttpResponse;
```

### HttpResponse

A `readonly` value object constructed with a status code, body, and headers.

```php
use Marko\Http\HttpResponse;

public function statusCode(): int;
public function body(): string;
public function headers(): array;
public function json(): mixed;          // throws JsonException on invalid JSON
public function isSuccessful(): bool;   // 2xx
public function isRedirect(): bool;     // 3xx
public function isClientError(): bool;  // 4xx
public function isServerError(): bool;  // 5xx
```

### Exceptions

| Exception | Description |
|-----------|-------------|
| `HttpException` | Base exception for HTTP errors --- provides `getResponse()` to access the underlying `HttpResponse` if available |
| `ConnectionException` | Thrown when the HTTP connection itself fails (extends `HttpException`) |
