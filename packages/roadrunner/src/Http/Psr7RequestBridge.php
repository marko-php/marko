<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Http;

use Marko\Roadrunner\Exceptions\RoadRunnerException;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\UploadedFile;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

class Psr7RequestBridge
{
    /**
     * @var list<string>
     */
    private const array FORM_ENCODED_BODY_METHODS = ['PUT', 'PATCH', 'DELETE'];

    /**
     * Temporary files written for uploads whose PSR-7 stream is not backed by a local file.
     *
     * @var list<string>
     */
    private array $temporaryFiles = [];

    /**
     * @throws RoadRunnerException when an uploaded file cannot be written to a temporary file
     */
    public function bridge(
        ServerRequestInterface $psr7Request,
    ): Request {
        $server = $this->buildServer($psr7Request);
        $body = (string) $psr7Request->getBody();

        return new Request(
            server: $server,
            query: $psr7Request->getQueryParams(),
            post: $this->resolvePost($psr7Request, $server, $body),
            body: $body,
            cookies: $psr7Request->getCookieParams(),
            files: $this->mapUploadedFiles($psr7Request->getUploadedFiles()),
        );
    }

    /**
     * Delete the temporary upload files this bridge wrote that were not moved away.
     * Called after every request so a long-running worker never accumulates them.
     */
    public function removeTemporaryFiles(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        $this->temporaryFiles = [];
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
            // Header names containing an underscore are dropped, matching nginx's default
            // (underscores_in_headers off). Otherwise `X_Forwarded_For` and `X-Forwarded-For`
            // would both normalise to HTTP_X_FORWARDED_FOR, letting a client override a
            // proxy-set header and spoof the client IP or scheme for trusted-proxy logic.
            if (str_contains((string) $name, '_')) {
                continue;
            }

            $normalized = strtoupper(str_replace('-', '_', $name));
            $value = implode(', ', $values);

            if (
                $normalized === 'CONTENT_TYPE'
                || $normalized === 'CONTENT_LENGTH'
            ) {
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
        if (
            $post === []
            && $body !== ''
            && in_array($method, self::FORM_ENCODED_BODY_METHODS, true)
        ) {
            $contentType = $server['CONTENT_TYPE'] ?? '';
            if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
                parse_str($body, $post);
            }
        }

        return $post;
    }

    /**
     * @param array<mixed> $uploadedFiles
     *
     * @return array<string, UploadedFile|array<mixed>>
     */
    private function mapUploadedFiles(
        array $uploadedFiles,
    ): array {
        $files = [];

        foreach ($uploadedFiles as $key => $uploadedFile) {
            if ($uploadedFile instanceof UploadedFileInterface) {
                if ($uploadedFile->getError() !== UPLOAD_ERR_NO_FILE) {
                    $files[$key] = $this->mapUploadedFile($uploadedFile);
                }

                continue;
            }

            if (is_array($uploadedFile)) {
                $children = $this->mapUploadedFiles($uploadedFile);

                if ($children !== []) {
                    $files[$key] = array_is_list($uploadedFile) ? array_values($children) : $children;
                }
            }
        }

        return $files;
    }

    private function mapUploadedFile(
        UploadedFileInterface $uploadedFile,
    ): UploadedFile {
        $error = $uploadedFile->getError();

        return new UploadedFile(
            tempPath: $error === UPLOAD_ERR_OK ? $this->localPathFor($uploadedFile) : '',
            clientFilename: $uploadedFile->getClientFilename() ?? '',
            clientMediaType: $uploadedFile->getClientMediaType() ?? '',
            size: $uploadedFile->getSize() ?? 0,
            error: $error,
        );
    }

    /**
     * RoadRunner already writes each upload to a temporary file and hands over a stream
     * opened on it; reuse that path. Any other stream is copied to a temporary file this
     * bridge owns and removes after the request.
     *
     * @throws RoadRunnerException
     */
    private function localPathFor(
        UploadedFileInterface $uploadedFile,
    ): string {
        $stream = $uploadedFile->getStream();
        $uri = $stream->getMetadata('uri');

        if (
            $stream->getMetadata('wrapper_type') === 'plainfile'
            && is_string($uri)
            && is_file($uri)
        ) {
            return $uri;
        }

        $path = tempnam(sys_get_temp_dir(), 'marko-upload-');
        if ($path === false) {
            throw RoadRunnerException::temporaryUploadFileUnwritable(sys_get_temp_dir());
        }

        $this->temporaryFiles[] = $path;

        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        $target = fopen($path, 'wb');
        if ($target === false) {
            throw RoadRunnerException::temporaryUploadFileUnwritable(dirname($path));
        }

        while (!$stream->eof()) {
            fwrite($target, $stream->read(1048576));
        }

        fclose($target);

        return $path;
    }
}
