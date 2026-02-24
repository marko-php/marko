<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Marko\Http\Contracts\HttpClientInterface;
use Marko\Http\Exceptions\ConnectionException;
use Marko\Http\Exceptions\HttpException;
use Marko\Http\Guzzle\GuzzleHttpClient;
use Marko\Http\HttpResponse;

function createTestableClient(
    MockHandler $mock,
    array &$history = [],
): GuzzleHttpClient {
    $handlerStack = HandlerStack::create($mock);
    $handlerStack->push(Middleware::history($history));
    $guzzle = new Client(['handler' => $handlerStack]);

    return new class ($guzzle) extends GuzzleHttpClient
    {
        public function __construct(
            private readonly GuzzleClientInterface $testClient,
        ) {}

        protected function createClient(): GuzzleClientInterface
        {
            return $this->testClient;
        }
    };
}

describe('GuzzleHttpClient', function (): void {
    it('implements HttpClientInterface', function (): void {
        $client = new GuzzleHttpClient();

        expect($client)->toBeInstanceOf(HttpClientInterface::class);
    });

    it('sends GET request and returns response', function (): void {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'text/plain'], 'Hello World'),
        ]);
        $history = [];
        $client = createTestableClient($mock, $history);

        $response = $client->get('https://example.com/api');

        expect($response)->toBeInstanceOf(HttpResponse::class)
            ->and($response->statusCode())->toBe(200)
            ->and($response->body())->toBe('Hello World')
            ->and($response->headers())->toHaveKey('Content-Type')
            ->and($history)->toHaveCount(1)
            ->and($history[0]['request']->getMethod())->toBe('GET')
            ->and((string) $history[0]['request']->getUri())->toBe('https://example.com/api');
    });

    it('sends POST request with json body', function (): void {
        $mock = new MockHandler([
            new Response(201, [], '{"id":1}'),
        ]);
        $history = [];
        $client = createTestableClient($mock, $history);

        $response = $client->post('https://example.com/api/users', [
            'json' => ['name' => 'Marko'],
        ]);

        expect($response->statusCode())->toBe(201)
            ->and($response->json())->toBe(['id' => 1])
            ->and($history[0]['request']->getMethod())->toBe('POST')
            ->and((string) $history[0]['request']->getBody())->toBe('{"name":"Marko"}');
    });

    it('sends PUT request with body', function (): void {
        $mock = new MockHandler([
            new Response(200, [], '{"updated":true}'),
        ]);
        $history = [];
        $client = createTestableClient($mock, $history);

        $response = $client->put('https://example.com/api/users/1', [
            'body' => '{"name":"Updated"}',
        ]);

        expect($response->statusCode())->toBe(200)
            ->and($history[0]['request']->getMethod())->toBe('PUT');
    });

    it('sends DELETE request', function (): void {
        $mock = new MockHandler([
            new Response(204, [], ''),
        ]);
        $history = [];
        $client = createTestableClient($mock, $history);

        $response = $client->delete('https://example.com/api/users/1');

        expect($response->statusCode())->toBe(204)
            ->and($response->body())->toBe('')
            ->and($history[0]['request']->getMethod())->toBe('DELETE');
    });

    it('includes custom headers in request', function (): void {
        $mock = new MockHandler([
            new Response(200, [], ''),
        ]);
        $history = [];
        $client = createTestableClient($mock, $history);

        $client->get('https://example.com/api', [
            'headers' => ['Authorization' => 'Bearer token123'],
        ]);

        expect($history[0]['request']->getHeaderLine('Authorization'))->toBe('Bearer token123');
    });

    it('maps response status code', function (): void {
        $mock = new MockHandler([
            new Response(204, [], ''),
        ]);
        $client = createTestableClient($mock);

        $response = $client->get('https://example.com/api');

        expect($response->statusCode())->toBe(204)
            ->and($response->isSuccessful())->toBeTrue();
    });

    it('maps response headers', function (): void {
        $mock = new MockHandler([
            new Response(200, [
                'X-Custom' => 'value1',
                'X-Rate-Limit' => '100',
            ], ''),
        ]);
        $client = createTestableClient($mock);

        $response = $client->get('https://example.com/api');

        expect($response->headers())->toHaveKey('X-Custom')
            ->and($response->headers()['X-Custom'])->toBe('value1')
            ->and($response->headers()['X-Rate-Limit'])->toBe('100');
    });

    it('throws HttpException for error status codes', function (): void {
        $mock = new MockHandler([
            new RequestException(
                'Client error',
                new Request('GET', 'https://example.com/api'),
                new Response(404, [], 'Not Found'),
            ),
        ]);
        $client = createTestableClient($mock);

        try {
            $client->get('https://example.com/api');
            test()->fail('Expected HttpException to be thrown');
        } catch (HttpException $e) {
            expect($e->getMessage())->toBe('Client error')
                ->and($e->getResponse())->not->toBeNull()
                ->and($e->getResponse()->statusCode())->toBe(404)
                ->and($e->getResponse()->body())->toBe('Not Found');
        }
    });

    it('throws ConnectionException for network failures', function (): void {
        $mock = new MockHandler([
            new ConnectException(
                'Connection refused',
                new Request('GET', 'https://example.com/api'),
            ),
        ]);
        $client = createTestableClient($mock);

        expect(fn () => $client->get('https://example.com/api'))
            ->toThrow(ConnectionException::class, 'Connection refused');
    });
});
