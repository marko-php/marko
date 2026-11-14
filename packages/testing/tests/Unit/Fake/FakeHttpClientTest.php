<?php

declare(strict_types=1);

use Marko\Http\Contracts\HttpClientInterface;
use Marko\Http\Exceptions\ConnectionException;
use Marko\Http\Exceptions\HttpException;
use Marko\Http\Exceptions\InvalidRequestOptionException;
use Marko\Http\HttpResponse;
use Marko\Testing\Exceptions\AssertionFailedException;
use Marko\Testing\Fake\FakeHttpClient;
use Marko\Testing\Fake\Http\RecordedRequest;

describe('FakeHttpClient', function (): void {
    it('implements HttpClientInterface', function (): void {
        expect(new FakeHttpClient())->toBeInstanceOf(HttpClientInterface::class);
    });

    it('returns a stubbed response for an exact url', function (): void {
        $http = new FakeHttpClient();
        $http->stub('https://api.example.com/orders', new HttpResponse(200, '{"id":1}'));

        $response = $http->get('https://api.example.com/orders');

        expect($response->statusCode())->toBe(200)
            ->and($response->json())->toBe(['id' => 1]);
    });

    it('does not match an exact stub against a different url', function (): void {
        $http = new FakeHttpClient();
        $http->stub('https://api.example.com/orders', new HttpResponse(200, ''));

        expect(fn () => $http->get('https://api.example.com/orders/1'))
            ->toThrow(AssertionFailedException::class);
    });

    it('returns a stubbed response for a wildcard url pattern', function (): void {
        $http = new FakeHttpClient();
        $http->stub('https://api.example.com/orders/*', new HttpResponse(200, 'order'));
        $http->stub('*/customers?page=1', new HttpResponse(200, 'customers'));

        expect($http->get('https://api.example.com/orders/42')->body())->toBe('order')
            ->and($http->delete('https://api.example.com/orders/42/items/1')->body())->toBe('order')
            ->and($http->get('https://api.example.com/customers?page=1')->body())->toBe('customers');
    });

    it('uses the first registered stub that matches', function (): void {
        $http = new FakeHttpClient();
        $http->stub('https://api.example.com/orders/1', new HttpResponse(200, 'specific'));
        $http->stub('https://api.example.com/*', new HttpResponse(200, 'catch-all'));

        expect($http->get('https://api.example.com/orders/1')->body())->toBe('specific')
            ->and($http->get('https://api.example.com/other')->body())->toBe('catch-all');
    });

    it('returns queued responses sequentially for any url', function (): void {
        $http = new FakeHttpClient();
        $http->queue(new HttpResponse(201, 'first'), new HttpResponse(200, 'second'));

        $first = $http->post('https://a.example.com');
        $second = $http->get('https://b.example.com');

        expect($first->statusCode())->toBe(201)
            ->and($first->body())->toBe('first')
            ->and($second->body())->toBe('second')
            ->and(fn () => $http->get('https://c.example.com'))->toThrow(AssertionFailedException::class);
    });

    it('exposes repeated headers of a stubbed response through headerValues', function (): void {
        $cookies = ['a=1; Expires=Wed, 21 Oct 2026 07:28:00 GMT', 'b=2; Path=/'];
        $http = new FakeHttpClient();
        $http->stub('https://sso.example.com/*', new HttpResponse(200, '', headerValues: ['Set-Cookie' => $cookies]));

        expect($http->get('https://sso.example.com/handoff')->headerValues('set-cookie'))->toBe($cookies);
    });

    it('exposes repeated headers of a queued response through headerValues', function (): void {
        $cookies = ['a=1; Expires=Wed, 21 Oct 2026 07:28:00 GMT', 'b=2; Path=/'];
        $http = new FakeHttpClient();
        $http->queue(new HttpResponse(200, '', headerValues: ['Set-Cookie' => $cookies]));

        expect($http->get('https://sso.example.com/handoff')->headerValues('Set-Cookie'))->toBe($cookies);
    });

    it('prefers a matching stub over the queue', function (): void {
        $http = new FakeHttpClient();
        $http->stub('https://api.example.com/health', new HttpResponse(200, 'ok'));
        $http->queue(new HttpResponse(201, 'queued'));

        expect($http->get('https://api.example.com/health')->body())->toBe('ok')
            ->and($http->get('https://api.example.com/other')->body())->toBe('queued');
    });

    it('throws AssertionFailedException for an unmatched request by default', function (): void {
        $http = new FakeHttpClient();

        expect(fn () => $http->get('https://api.example.com/unknown'))
            ->toThrow(AssertionFailedException::class, 'Unexpected HTTP request: GET https://api.example.com/unknown');
    });

    it('returns an empty 200 response for unmatched requests when stray prevention is disabled', function (): void {
        $http = new FakeHttpClient();
        $http->preventStrayRequests(false);

        $response = $http->get('https://api.example.com/unknown');

        expect($response->statusCode())->toBe(200)
            ->and($response->body())->toBe('')
            ->and($http->requests)->toHaveCount(1);
    });

    it('throws a stubbed ConnectionException', function (): void {
        $http = new FakeHttpClient();
        $http->stub('https://api.example.com/*', new ConnectionException('timeout'));

        expect(fn () => $http->get('https://api.example.com/slow'))
            ->toThrow(ConnectionException::class, 'timeout');

        $http->assertSentCount(1);
    });

    it(
        'throws HttpException with the response attached for a stubbed 4xx unless http_errors is false',
        function (): void {
            $http = new FakeHttpClient();
            $http->stub('https://api.example.com/missing', new HttpResponse(404, 'Not Found'));
            $http->stub('https://api.example.com/fail', new HttpResponse(500, 'boom'));

            try {
                $http->get('https://api.example.com/missing');
                test()->fail('Expected HttpException');
            } catch (HttpException $e) {
                expect($e->getResponse())->not->toBeNull()
                    ->and($e->getResponse()->statusCode())->toBe(404)
                    ->and($e->getMessage())->toContain('404');
            }

            $notFound = $http->get('https://api.example.com/missing', ['http_errors' => false]);
            $serverError = $http->get('https://api.example.com/fail', ['http_errors' => false]);

            expect($notFound->isClientError())->toBeTrue()
                ->and($serverError->isServerError())->toBeTrue()
                ->and(fn () => $http->get('https://api.example.com/fail'))->toThrow(HttpException::class);
        },
    );

    it('validates options with the shared RequestOptions rules', function (): void {
        $http = new FakeHttpClient();
        $http->preventStrayRequests(false);

        expect(fn () => $http->post('https://api.example.com', ['form_param' => []]))
            ->toThrow(InvalidRequestOptionException::class, 'form_param')
            ->and(fn () => $http->post('https://api.example.com', ['json' => [], 'body' => '']))
            ->toThrow(InvalidRequestOptionException::class)
            ->and(fn () => $http->get('https://api.example.com', ['guzzle' => []]))
            ->toThrow(InvalidRequestOptionException::class, 'guzzle');

        $http->assertNothingSent();
    });

    it('records requests with an uppercase method, url and options', function (): void {
        $http = new FakeHttpClient();
        $http->preventStrayRequests(false);

        $http->request('patch', 'https://api.example.com/orders/1', ['json' => ['status' => 'paid']]);

        expect($http->requests)->toHaveCount(1)
            ->and($http->requests[0])->toBeInstanceOf(RecordedRequest::class)
            ->and($http->requests[0]->method)->toBe('PATCH')
            ->and($http->requests[0]->url)->toBe('https://api.example.com/orders/1')
            ->and($http->requests[0]->json())->toBe(['status' => 'paid']);
    });

    it('passes assertSent, assertSentCount, assertNotSent and assertNothingSent when they hold', function (): void {
        $http = new FakeHttpClient();
        $http->assertNothingSent();
        $http->assertSentCount(0);

        $http->stub('https://api.example.com/*', new HttpResponse(201, ''));
        $http->post('https://api.example.com/orders', ['json' => ['id' => 1]]);

        $http->assertSent();
        $http->assertSent(fn (RecordedRequest $r) => $r->method === 'POST'
            && $r->json()['id'] === 1);
        $http->assertSentCount(1);
        $http->assertNotSent(fn (RecordedRequest $r) => str_contains($r->url, '/fail'));

        expect($http->requests)->toHaveCount(1);
    });

    it('fails assertSent when nothing matches', function (): void {
        $http = new FakeHttpClient();

        expect(fn () => $http->assertSent())->toThrow(AssertionFailedException::class);

        $http->preventStrayRequests(false);
        $http->get('https://api.example.com');

        expect(fn () => $http->assertSent(fn (RecordedRequest $r) => $r->method === 'POST'))
            ->toThrow(AssertionFailedException::class);
    });

    it('fails assertSentCount when the count differs', function (): void {
        $http = new FakeHttpClient();

        expect(fn () => $http->assertSentCount(1))
            ->toThrow(AssertionFailedException::class, 'Expected 1 requests but got 0.');
    });

    it('fails assertNotSent when a request matches', function (): void {
        $http = new FakeHttpClient();
        $http->preventStrayRequests(false);
        $http->get('https://api.example.com/fail');

        expect(fn () => $http->assertNotSent(fn (RecordedRequest $r) => str_contains($r->url, '/fail')))
            ->toThrow(AssertionFailedException::class);
    });

    it('fails assertNothingSent when a request was sent', function (): void {
        $http = new FakeHttpClient();
        $http->preventStrayRequests(false);
        $http->get('https://api.example.com');

        expect(fn () => $http->assertNothingSent())->toThrow(AssertionFailedException::class);
    });

    it('clears recorded requests, stubs and queued responses', function (): void {
        $http = new FakeHttpClient();
        $http->stub('https://api.example.com', new HttpResponse(200, ''));
        $http->queue(new HttpResponse(200, ''));
        $http->get('https://api.example.com');

        $http->clear();

        $http->assertNothingSent();
        expect(fn () => $http->get('https://api.example.com'))->toThrow(AssertionFailedException::class);
    });
});
