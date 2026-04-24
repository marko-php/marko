---
title: marko/http-guzzle
description: Guzzle-powered HTTP client driver — makes real HTTP requests using the battle-tested Guzzle library.
---

Guzzle-powered HTTP client driver --- makes real HTTP requests using the battle-tested Guzzle library. Implements `HttpClientInterface` from [`marko/http`](/docs/packages/http/) using Guzzle under the hood. Connection failures throw `ConnectionException`; HTTP errors throw `HttpException` with the response attached.

## Installation

```bash
composer require marko/http-guzzle
```

This automatically installs `marko/http`.

## Usage

### Automatic via Binding

Bind the interface to the Guzzle implementation in your `module.php`:

```php title="module.php"
use Marko\Http\Contracts\HttpClientInterface;
use Marko\Http\Guzzle\GuzzleHttpClient;

return [
    'bindings' => [
        HttpClientInterface::class => GuzzleHttpClient::class,
    ],
];
```

Then inject `HttpClientInterface` anywhere:

```php
use Marko\Http\Contracts\HttpClientInterface;

class ApiClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
    ) {}

    public function fetchData(): array
    {
        $response = $this->httpClient->get('https://api.example.com/data', [
            'timeout' => 10,
            'headers' => ['Accept' => 'application/json'],
        ]);

        return $response->json();
    }
}
```

### Handling Errors

```php
use Marko\Http\Exceptions\ConnectionException;
use Marko\Http\Exceptions\HttpException;

try {
    $response = $this->httpClient->get('https://api.example.com/resource');
} catch (ConnectionException $e) {
    // Network failure (DNS, timeout, etc.)
} catch (HttpException $e) {
    // HTTP error (4xx, 5xx) --- response may be available
    $errorResponse = $e->getResponse();
}
```

### Supported Options

The following request options are forwarded to Guzzle:

| Option | Description |
|---|---|
| `headers` | Associative array of HTTP headers |
| `body` | Raw request body |
| `json` | Data to send as JSON (automatically sets `Content-Type`) |
| `query` | Query string parameters |
| `timeout` | Request timeout in seconds |

## Customization

Extend `GuzzleHttpClient` via Preference to customize the underlying Guzzle client:

```php
use Marko\Core\Attributes\Preference;
use Marko\Http\Guzzle\GuzzleHttpClient;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface as GuzzleClientInterface;

#[Preference(replaces: GuzzleHttpClient::class)]
class CustomGuzzleClient extends GuzzleHttpClient
{
    protected function createClient(): GuzzleClientInterface
    {
        return new Client([
            'base_uri' => 'https://api.example.com',
            'timeout' => 30,
        ]);
    }
}
```

The `createClient()` method is called lazily on first request --- override it to set base URIs, default timeouts, middleware, or any other Guzzle configuration.

## API Reference

### GuzzleHttpClient

Implements `HttpClientInterface`. See [`marko/http`](/docs/packages/http/) for the full contract.

| Method | Description |
|---|---|
| `request(string $method, string $url, array $options = []): HttpResponse` | Send a request with any HTTP method |
| `get(string $url, array $options = []): HttpResponse` | Send a GET request |
| `post(string $url, array $options = []): HttpResponse` | Send a POST request |
| `put(string $url, array $options = []): HttpResponse` | Send a PUT request |
| `patch(string $url, array $options = []): HttpResponse` | Send a PATCH request |
| `delete(string $url, array $options = []): HttpResponse` | Send a DELETE request |
| `createClient(): GuzzleClientInterface` | Protected --- override via Preference to customize the Guzzle client |

All methods throw `ConnectionException` on network failures and `HttpException` on HTTP error responses (4xx, 5xx). The `HttpException` carries the `HttpResponse` when available.
