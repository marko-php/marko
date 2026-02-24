<?php

declare(strict_types=1);

namespace Marko\Http\Guzzle;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use GuzzleHttp\Exception\ConnectException as GuzzleConnectException;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use Marko\Http\Contracts\HttpClientInterface;
use Marko\Http\Exceptions\ConnectionException;
use Marko\Http\Exceptions\HttpException;
use Marko\Http\HttpResponse;

class GuzzleHttpClient implements HttpClientInterface
{
    private ?GuzzleClientInterface $client = null;

    /**
     * @param array<string, mixed> $options
     *
     * @throws HttpException|ConnectionException
     */
    public function request(
        string $method,
        string $url,
        array $options = [],
    ): HttpResponse {
        try {
            $guzzleOptions = $this->buildOptions($options);
            $response = $this->client()->request($method, $url, $guzzleOptions);

            return new HttpResponse(
                statusCode: $response->getStatusCode(),
                body: (string) $response->getBody(),
                headers: $this->flattenHeaders($response->getHeaders()),
            );
        } catch (GuzzleConnectException $e) {
            throw new ConnectionException($e->getMessage(), previous: $e);
        } catch (GuzzleRequestException $e) {
            $response = $e->getResponse();
            $httpResponse = $response !== null
                ? new HttpResponse(
                    statusCode: $response->getStatusCode(),
                    body: (string) $response->getBody(),
                    headers: $this->flattenHeaders($response->getHeaders()),
                )
                : null;

            throw new HttpException($e->getMessage(), $httpResponse, previous: $e);
        }
    }

    /**
     * @param array<string, mixed> $options
     *
     * @throws HttpException|ConnectionException
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
     * @throws HttpException|ConnectionException
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
     * @throws HttpException|ConnectionException
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
     * @throws HttpException|ConnectionException
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
     * @throws HttpException|ConnectionException
     */
    public function delete(
        string $url,
        array $options = [],
    ): HttpResponse {
        return $this->request('DELETE', $url, $options);
    }

    protected function createClient(): GuzzleClientInterface
    {
        return new Client();
    }

    private function client(): GuzzleClientInterface
    {
        return $this->client ??= $this->createClient();
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function buildOptions(
        array $options,
    ): array {
        $guzzleOptions = ['http_errors' => true];

        if (isset($options['headers'])) {
            $guzzleOptions['headers'] = $options['headers'];
        }

        if (isset($options['body'])) {
            $guzzleOptions['body'] = $options['body'];
        }

        if (isset($options['json'])) {
            $guzzleOptions['json'] = $options['json'];
        }

        if (isset($options['query'])) {
            $guzzleOptions['query'] = $options['query'];
        }

        if (isset($options['timeout'])) {
            $guzzleOptions['timeout'] = $options['timeout'];
        }

        return $guzzleOptions;
    }

    /**
     * @param array<string, array<string>> $headers
     *
     * @return array<string, string>
     */
    private function flattenHeaders(
        array $headers,
    ): array {
        $flat = [];

        foreach ($headers as $name => $values) {
            $flat[$name] = implode(', ', $values);
        }

        return $flat;
    }
}
