# Marko HTTP Guzzle

Guzzle-powered HTTP client driver--makes real HTTP requests using the battle-tested Guzzle library.

## Overview

This package implements `HttpClientInterface` using Guzzle. Install it alongside `marko/http` and bind it in your module to get a working HTTP client. Connection failures throw `ConnectionException`; HTTP errors throw `HttpException` with the response attached.

## Installation

```bash
composer require marko/http-guzzle
```

## Usage

### Automatic via Binding

Bind the interface to the Guzzle implementation in your `module.php`:

```php
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
        private HttpClientInterface $http,
    ) {}

    public function fetchData(): array
    {
        $response = $this->http->get('https://api.example.com/data', [
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
    $response = $this->http->get('https://api.example.com/resource');
} catch (ConnectionException $e) {
    // Network failure (DNS, timeout, etc.)
} catch (HttpException $e) {
    // HTTP error (4xx, 5xx) -- response may be available
    $errorResponse = $e->getResponse();
}
```

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

## API Reference

### GuzzleHttpClient

Implements `HttpClientInterface`. See `marko/http` for the full method list.

```php
public function request(string $method, string $url, array $options = []): HttpResponse;
public function get(string $url, array $options = []): HttpResponse;
public function post(string $url, array $options = []): HttpResponse;
public function put(string $url, array $options = []): HttpResponse;
public function patch(string $url, array $options = []): HttpResponse;
public function delete(string $url, array $options = []): HttpResponse;
```
