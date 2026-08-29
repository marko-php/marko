<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Http;

use Marko\Roadrunner\Exceptions\UploadedFilesNotSupportedException;
use Marko\Routing\Http\Request;
use Psr\Http\Message\ServerRequestInterface;

class Psr7RequestBridge
{
    /**
     * @var list<string>
     */
    private const array FORM_ENCODED_BODY_METHODS = ['PUT', 'PATCH', 'DELETE'];

    /**
     * @throws UploadedFilesNotSupportedException
     */
    public function bridge(
        ServerRequestInterface $psr7Request,
    ): Request {
        if ($psr7Request->getUploadedFiles() !== []) {
            throw UploadedFilesNotSupportedException::whenBridgingRequest();
        }

        $server = $this->buildServer($psr7Request);
        $body = (string) $psr7Request->getBody();

        return new Request(
            server: $server,
            query: $psr7Request->getQueryParams(),
            post: $this->resolvePost($psr7Request, $server, $body),
            body: $body,
            cookies: $psr7Request->getCookieParams(),
        );
    }

    /**
     * @return array<string, string>
     */
    private function buildServer(
        ServerRequestInterface $psr7Request,
    ): array {
        $uri = $psr7Request->getUri();
        $path = $uri->getPath() !== '' ? $uri->getPath() : '/';
        $query = $uri->getQuery();

        $server = [
            'REQUEST_METHOD' => $psr7Request->getMethod(),
            'REQUEST_URI' => $query !== '' ? "$path?$query" : $path,
            'QUERY_STRING' => $query,
        ];

        if ($uri->getScheme() === 'https') {
            $server['HTTPS'] = 'on';
        }

        $remoteAddr = $psr7Request->getServerParams()['REMOTE_ADDR'] ?? null;
        if ($remoteAddr !== null) {
            $server['REMOTE_ADDR'] = (string) $remoteAddr;
        }

        foreach ($psr7Request->getHeaders() as $name => $values) {
            $normalized = strtoupper(str_replace('-', '_', $name));
            $value = implode(', ', $values);

            if ($normalized === 'CONTENT_TYPE' || $normalized === 'CONTENT_LENGTH') {
                $server[$normalized] = $value;
                continue;
            }

            $server['HTTP_' . $normalized] = $value;
        }

        return $server;
    }

    /**
     * PHP does not populate a parsed body for PUT/PATCH/DELETE requests carrying
     * a form-urlencoded body, mirroring Request::fromGlobals()'s equivalent handling.
     *
     * @param array<string, string> $server
     *
     * @return array<string, mixed>
     */
    private function resolvePost(
        ServerRequestInterface $psr7Request,
        array $server,
        string $body,
    ): array {
        $parsedBody = $psr7Request->getParsedBody();
        $post = is_array($parsedBody) ? $parsedBody : [];

        $method = strtoupper($psr7Request->getMethod());
        if ($post === [] && $body !== '' && in_array($method, self::FORM_ENCODED_BODY_METHODS, true)) {
            $contentType = $server['CONTENT_TYPE'] ?? '';
            if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
                parse_str($body, $post);
            }
        }

        return $post;
    }
}
