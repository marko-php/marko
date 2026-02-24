# Marko HTTP Client

Contracts for HTTP requests--type-hint against `HttpClientInterface` so your code works with any HTTP driver.

## Overview

This package defines the `HttpClientInterface` and `HttpResponse` value object. It contains no implementation; install a driver like `marko/http-guzzle` for the actual HTTP calls. Your module code depends on the interface, making it easy to swap drivers or mock in tests.

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
        private HttpClientInterface $http,
    ) {}

    public function charge(
        float $amount,
    ): array {
        $response = $this->http->post('https://api.payments.com/charge', [
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
$response = $this->http->get('https://api.example.com/users');

if ($response->isSuccessful()) {
    $users = $response->json();
}

if ($response->isClientError()) {
    // Handle 4xx
}
```

### Request Options

All methods accept an `$options` array supporting:

- `headers` -- Request headers
- `body` -- Raw request body
- `json` -- JSON-encoded body
- `query` -- Query string parameters
- `timeout` -- Request timeout in seconds

## API Reference

### HttpClientInterface

```php
interface HttpClientInterface
{
    public function request(string $method, string $url, array $options = []): HttpResponse;
    public function get(string $url, array $options = []): HttpResponse;
    public function post(string $url, array $options = []): HttpResponse;
    public function put(string $url, array $options = []): HttpResponse;
    public function patch(string $url, array $options = []): HttpResponse;
    public function delete(string $url, array $options = []): HttpResponse;
}
```

### HttpResponse

```php
public function statusCode(): int;
public function body(): string;
public function headers(): array;
public function json(): mixed;
public function isSuccessful(): bool;
public function isRedirect(): bool;
public function isClientError(): bool;
public function isServerError(): bool;
```
