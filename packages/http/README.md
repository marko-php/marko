# marko/http

Contracts for HTTP requests — type-hint against `HttpClientInterface` so your code works with any HTTP driver.

## Installation

```bash
composer require marko/http
```

Note: You also need an implementation package such as `marko/http-guzzle`.

## Quick Example

```php
use Marko\Http\Contracts\HttpClientInterface;

class PaymentGateway
{
    public function __construct(
        private HttpClientInterface $httpClient,
    ) {}

    public function charge(float $amount): array
    {
        $response = $this->httpClient->post('https://api.payments.com/charge', [
            'json' => ['amount' => $amount],
        ]);

        return $response->json();
    }
}
```

## Documentation

Full usage, API reference, and examples: [marko/http](https://marko.build/docs/packages/http/)
