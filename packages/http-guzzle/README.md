# marko/http-guzzle

Guzzle-powered HTTP client driver--makes real HTTP requests using the battle-tested Guzzle library.

## Installation

```bash
composer require marko/http-guzzle
```

## Quick Example

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
            'headers' => ['Accept' => 'application/json'],
        ]);

        return $response->json();
    }
}
```

## Documentation

Full usage, API reference, and examples: [marko/http-guzzle](https://marko.build/docs/packages/http-guzzle/)
