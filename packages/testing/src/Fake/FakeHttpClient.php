<?php

declare(strict_types=1);

namespace Marko\Testing\Fake;

use Marko\Http\Contracts\HttpClientInterface;
use Marko\Http\Exceptions\ConnectionException;
use Marko\Http\Exceptions\HttpException;
use Marko\Http\Exceptions\InvalidRequestOptionException;
use Marko\Http\HttpResponse;
use Marko\Http\RequestOptions;
use Marko\Testing\Exceptions\AssertionFailedException;
use Marko\Testing\Fake\Http\RecordedRequest;

/**
 * In-memory HttpClientInterface for tests.
 *
 * Responses are resolved in this order: the first registered stub whose URL
 * pattern matches, then the next queued response, then stray handling
 * (throw by default, or an empty 200 response when stray requests are allowed).
 */
class FakeHttpClient implements HttpClientInterface
{
    /** @var array<RecordedRequest> */
    public private(set) array $requests = [];

    /** @var array<array{pattern: string, response: HttpResponse|HttpException}> */
    private array $stubs = [];

    /** @var array<HttpResponse|HttpException> */
    private array $queued = [];

    private bool $preventStrayRequests = true;

    /**
     * Respond to every request whose URL matches $urlPattern. Use * as a wildcard.
     * Pass an HttpException (e.g. ConnectionException) to simulate a failure.
     */
    public function stub(
        string $urlPattern,
        HttpResponse|HttpException $response,
    ): self {
        $this->stubs[] = ['pattern' => $urlPattern, 'response' => $response];

        return $this;
    }

    /**
     * Queue responses returned in order for requests that match no stub, whatever their URL.
     */
    public function queue(
        HttpResponse|HttpException ...$responses,
    ): self {
        array_push($this->queued, ...$responses);

        return $this;
    }

    /**
     * When enabled (the default), a request matching no stub or queued response
     * throws AssertionFailedException. When disabled, it returns an empty 200 response.
     */
    public function preventStrayRequests(
        bool $prevent = true,
    ): self {
        $this->preventStrayRequests = $prevent;

        return $this;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @throws HttpException|ConnectionException|InvalidRequestOptionException|AssertionFailedException
     */
    public function request(
        string $method,
        string $url,
        array $options = [],
    ): HttpResponse {
        RequestOptions::validate($options);

        $method = strtoupper($method);
        $response = $this->resolve($method, $url);

        $this->requests[] = new RecordedRequest($method, $url, $options);

        if ($response instanceof HttpException) {
            throw $response;
        }

        $isError = $response->isClientError()
            || $response->isServerError();

        if (
            $isError
            && RequestOptions::throwsOnHttpError($options)
        ) {
            throw new HttpException(
                "HTTP request returned status code {$response->statusCode()}: $method $url",
                $response,
            );
        }

        return $response;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @throws HttpException|ConnectionException|InvalidRequestOptionException|AssertionFailedException
     */
    public function get(
        string $url,
        array $options = [],
    ): HttpResponse {
        return $this->request('GET', $url, $options);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @throws HttpException|ConnectionException|InvalidRequestOptionException|AssertionFailedException
     */
    public function post(
        string $url,
        array $options = [],
    ): HttpResponse {
        return $this->request('POST', $url, $options);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @throws HttpException|ConnectionException|InvalidRequestOptionException|AssertionFailedException
     */
    public function put(
        string $url,
        array $options = [],
    ): HttpResponse {
        return $this->request('PUT', $url, $options);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @throws HttpException|ConnectionException|InvalidRequestOptionException|AssertionFailedException
     */
    public function patch(
        string $url,
        array $options = [],
    ): HttpResponse {
        return $this->request('PATCH', $url, $options);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @throws HttpException|ConnectionException|InvalidRequestOptionException|AssertionFailedException
     */
    public function delete(
        string $url,
        array $options = [],
    ): HttpResponse {
        return $this->request('DELETE', $url, $options);
    }

    /**
     * @param (callable(RecordedRequest): bool)|null $callback
     *
     * @throws AssertionFailedException
     */
    public function assertSent(
        ?callable $callback = null,
    ): void {
        $matched = $callback === null
            ? $this->requests !== []
            : array_any($this->requests, fn (RecordedRequest $request): bool => (bool) $callback($request));

        if (!$matched) {
            throw AssertionFailedException::unexpectedEmpty('matching requests');
        }
    }

    /**
     * @param callable(RecordedRequest): bool $callback
     *
     * @throws AssertionFailedException
     */
    public function assertNotSent(
        callable $callback,
    ): void {
        if (array_any($this->requests, fn (RecordedRequest $request): bool => (bool) $callback($request))) {
            throw AssertionFailedException::unexpectedContains('requests', 'a request matching the callback');
        }
    }

    /**
     * @throws AssertionFailedException
     */
    public function assertSentCount(
        int $expected,
    ): void {
        $actual = count($this->requests);

        if ($actual !== $expected) {
            throw AssertionFailedException::expectedCount('requests', $expected, $actual);
        }
    }

    /**
     * @throws AssertionFailedException
     */
    public function assertNothingSent(): void
    {
        if ($this->requests !== []) {
            throw AssertionFailedException::expectedEmpty('requests');
        }
    }

    public function clear(): void
    {
        $this->requests = [];
        $this->stubs = [];
        $this->queued = [];
    }

    /**
     * @throws AssertionFailedException
     */
    private function resolve(
        string $method,
        string $url,
    ): HttpResponse|HttpException {
        $stub = array_find($this->stubs, fn (array $stub): bool => $this->matches($stub['pattern'], $url));

        if ($stub !== null) {
            return $stub['response'];
        }

        if ($this->queued !== []) {
            return array_shift($this->queued);
        }

        if ($this->preventStrayRequests) {
            throw AssertionFailedException::strayRequest($method, $url);
        }

        return new HttpResponse(200, '');
    }

    private function matches(
        string $pattern,
        string $url,
    ): bool {
        $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#';

        return preg_match($regex, $url) === 1;
    }
}
