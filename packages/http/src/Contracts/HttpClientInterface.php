<?php

declare(strict_types=1);

namespace Marko\Http\Contracts;

use Marko\Http\Exceptions\ConnectionException;
use Marko\Http\Exceptions\HttpException;
use Marko\Http\HttpResponse;

interface HttpClientInterface
{
    /**
     * @param array<string, mixed> $options
     *
     * @throws HttpException|ConnectionException
     */
    public function request(
        string $method,
        string $url,
        array $options = [],
    ): HttpResponse;

    /**
     * @param array<string, mixed> $options
     *
     * @throws HttpException|ConnectionException
     */
    public function get(
        string $url,
        array $options = [],
    ): HttpResponse;

    /**
     * @param array<string, mixed> $options
     *
     * @throws HttpException|ConnectionException
     */
    public function post(
        string $url,
        array $options = [],
    ): HttpResponse;

    /**
     * @param array<string, mixed> $options
     *
     * @throws HttpException|ConnectionException
     */
    public function put(
        string $url,
        array $options = [],
    ): HttpResponse;

    /**
     * @param array<string, mixed> $options
     *
     * @throws HttpException|ConnectionException
     */
    public function patch(
        string $url,
        array $options = [],
    ): HttpResponse;

    /**
     * @param array<string, mixed> $options
     *
     * @throws HttpException|ConnectionException
     */
    public function delete(
        string $url,
        array $options = [],
    ): HttpResponse;
}
