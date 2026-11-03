<?php

declare(strict_types=1);

namespace Marko\Routing\Http;

use Marko\Routing\Exceptions\MalformedJsonException;
use Marko\Routing\Exceptions\RequestBodyTooLargeException;
use Marko\Routing\Exceptions\UploadedFileException;
use NoDiscard;

readonly class Request
{
    /**
     * The raw body, read on first access ({@see body()}, {@see json()}), never when the request is built.
     */
    private RequestBody $body;

    /**
     * @param array<string, mixed> $server
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, string> $cookies
     * @param string|RequestBody $body the raw body, or a RequestBody that reads it on first access
     * @param array<string, UploadedFile|array<mixed>> $files uploaded files, keyed like the form fields that sent them
     */
    public function __construct(
        private array $server = [],
        private array $query = [],
        private array $post = [],
        string|RequestBody $body = '',
        private ?string $controller = null,
        private ?string $action = null,
        private array $cookies = [],
        private array $files = [],
    ) {
        $this->body = $body instanceof RequestBody ? $body : RequestBody::fromString($body);
    }

    public static function fromGlobals(): self
    {
        // The body is read on first access, so an upload (or any body the app never reads) is not copied into memory
        $body = RequestBody::fromGlobals($_SERVER);
        $post = $_POST;

        // PHP only populates $_POST for POST requests; parse body for PUT/PATCH/DELETE
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
        if (
            $post === []
            && in_array($method, ['PUT', 'PATCH', 'DELETE'], true)
            && str_contains($contentType, 'application/x-www-form-urlencoded')
        ) {
            parse_str($body->contents(), $post);
        }

        return new self(
            server: $_SERVER,
            query: $_GET,
            post: $post,
            body: $body,
            cookies: $_COOKIE,
            files: self::normalizeFiles($_FILES),
        );
    }

    public function method(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    public function path(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        $position = strpos($uri, '?');

        return $position === false ? $uri : substr($uri, 0, $position);
    }

    /**
     * @return ($key is null ? array<string, mixed> : mixed)
     */
    public function query(
        ?string $key = null,
        mixed $default = null,
    ): mixed {
        if ($key === null) {
            return $this->query;
        }

        return $this->query[$key] ?? $default;
    }

    /**
     * @return ($key is null ? array<string, mixed> : mixed)
     */
    public function post(
        ?string $key = null,
        mixed $default = null,
    ): mixed {
        if ($key === null) {
            return $this->post;
        }

        return $this->post[$key] ?? $default;
    }

    /**
     * @return ($key is null ? array<string, string> : mixed)
     */
    public function cookie(
        ?string $key = null,
        mixed $default = null,
    ): mixed {
        if ($key === null) {
            return $this->cookies;
        }

        return $this->cookies[$key] ?? $default;
    }

    /**
     * The raw request body, read on first access.
     *
     * @throws RequestBodyTooLargeException when the request declared a body over post_max_size
     */
    public function body(): string
    {
        return $this->body->contents();
    }

    /**
     * Read a single uploaded file by field name; nested fields use dot-notation (`user.avatar`).
     *
     * @throws UploadedFileException when the field holds several files (use files() instead)
     */
    public function file(
        string $key,
    ): ?UploadedFile {
        $file = self::dataGet($this->files, $key);

        if (is_array($file)) {
            throw UploadedFileException::multipleFilesForKey($key);
        }

        return $file instanceof UploadedFile ? $file : null;
    }

    /**
     * With no key, every uploaded file keyed like the form fields that sent them.
     * With a key, the files for that field as a list — a single file is wrapped in one.
     *
     * @return array<mixed>
     */
    public function files(
        ?string $key = null,
    ): array {
        if ($key === null) {
            return $this->files;
        }

        $files = self::dataGet($this->files, $key);

        if ($files instanceof UploadedFile) {
            return [$files];
        }

        return is_array($files) ? $files : [];
    }

    /**
     * Whether at least one file was uploaded for the field. Check isValid() on the file
     * before using it: an upload that failed (e.g. too large) is still present.
     */
    public function hasFile(
        string $key,
    ): bool {
        return $this->files($key) !== [];
    }

    /**
     * Whether the request body is JSON: a Content-Type of `application/json` or a `+json` structured suffix.
     */
    public function isJson(): bool
    {
        return $this->isJsonMediaType($this->mediaType($this->header('Content-Type', '')));
    }

    /**
     * Whether the client asked for a JSON response via its Accept header.
     */
    public function wantsJson(): bool
    {
        $accept = $this->header('Accept', '');

        return array_any(
            explode(',', $accept),
            fn (string $mediaRange): bool => $this->isJsonMediaType($this->mediaType($mediaRange)),
        );
    }

    /**
     * Read the decoded JSON body, or a value from it by dot-notation key (`user.email`).
     *
     * Only JSON requests ({@see isJson()}) are decoded; other requests return an empty
     * payload. The body is read and decoded on first access, once. A malformed JSON body
     * throws when it is read, never silently.
     *
     * @throws MalformedJsonException|RequestBodyTooLargeException
     */
    public function json(
        ?string $key = null,
        mixed $default = null,
    ): mixed {
        [$json, $jsonError] = $this->isJson() ? $this->body->decodeJson() : [[], null];

        if ($jsonError !== null) {
            throw MalformedJsonException::fromBody(strlen($this->body->contents()), $jsonError);
        }

        if ($key === null) {
            return $json;
        }

        return self::dataGet($json, $key, $default);
    }

    /**
     * Read request input: the JSON body for JSON requests, form data otherwise, then the query string.
     *
     * With no key, returns the query string merged with the body (body values win).
     *
     * @return ($key is null ? array<string, mixed> : mixed)
     * @throws MalformedJsonException|RequestBodyTooLargeException
     */
    public function input(
        ?string $key = null,
        mixed $default = null,
    ): mixed {
        $bodyInput = $this->bodyInput();

        if ($key === null) {
            return array_replace($this->query, $bodyInput);
        }

        return self::dataGet($bodyInput, $key)
            ?? self::dataGet($this->query, $key, $default);
    }

    public function server(
        string $key,
    ): ?string {
        $value = $this->server[$key] ?? null;

        return $value !== null ? (string) $value : null;
    }

    public function ip(): ?string
    {
        return $this->server('REMOTE_ADDR');
    }

    #[NoDiscard]
    public function withRoute(
        string $controller,
        string $action,
    ): self {
        return clone($this, [
            'controller' => $controller,
            'action' => $action,
        ]);
    }

    public function controller(): ?string
    {
        return $this->controller;
    }

    public function action(): ?string
    {
        return $this->action;
    }

    public function header(
        string $name,
        ?string $default = null,
    ): ?string {
        $normalized = strtoupper(str_replace('-', '_', $name));
        $serverKey = 'HTTP_' . $normalized;

        if (isset($this->server[$serverKey])) {
            return (string) $this->server[$serverKey];
        }

        if (($normalized === 'CONTENT_TYPE' || $normalized === 'CONTENT_LENGTH') && isset($this->server[$normalized])) {
            return (string) $this->server[$normalized];
        }

        return $default;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        $headers = [];

        foreach ($this->server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headerName = str_replace('_', '-', substr($key, 5));
                $headerName = ucwords(strtolower($headerName), '-');
                $headers[$headerName] = $value;
            }
        }

        return $headers;
    }

    /**
     * Convert PHP's $_FILES layout, including the inverted `name[]` / `name[key]` shape PHP
     * produces for multi-file and nested inputs, into a tree of UploadedFile objects keyed like
     * the form fields. Inputs submitted without a file (UPLOAD_ERR_NO_FILE) are left out.
     *
     * @param array<string, mixed> $files
     * @return array<string, UploadedFile|array<mixed>>
     */
    private static function normalizeFiles(
        array $files,
    ): array {
        $normalized = [];

        foreach ($files as $field => $spec) {
            if (!is_array($spec) || !isset($spec['name'], $spec['tmp_name'], $spec['error'])) {
                continue;
            }

            $file = self::normalizeFileSpec(
                $spec['name'],
                $spec['type'] ?? '',
                $spec['tmp_name'],
                $spec['error'],
                $spec['size'] ?? 0,
            );

            if ($file !== null) {
                $normalized[$field] = $file;
            }
        }

        return $normalized;
    }

    /**
     * @return UploadedFile|array<mixed>|null
     */
    private static function normalizeFileSpec(
        mixed $name,
        mixed $type,
        mixed $tmpName,
        mixed $error,
        mixed $size,
    ): UploadedFile|array|null {
        if (!is_array($name)) {
            if ((int) $error === UPLOAD_ERR_NO_FILE) {
                return null;
            }

            return new UploadedFile(
                tempPath: (string) $tmpName,
                clientFilename: (string) $name,
                clientMediaType: (string) $type,
                size: (int) $size,
                error: (int) $error,
            );
        }

        $children = [];

        foreach ($name as $key => $childName) {
            $child = self::normalizeFileSpec(
                $childName,
                is_array($type) ? ($type[$key] ?? '') : '',
                is_array($tmpName) ? ($tmpName[$key] ?? '') : '',
                is_array($error) ? ($error[$key] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE,
                is_array($size) ? ($size[$key] ?? 0) : 0,
            );

            if ($child !== null) {
                $children[$key] = $child;
            }
        }

        if ($children === []) {
            return null;
        }

        // Re-index list inputs (`photos[]`) so a skipped empty slot leaves no gap
        return array_is_list($name) ? array_values($children) : $children;
    }

    /**
     * @return array<mixed>
     * @throws MalformedJsonException|RequestBodyTooLargeException
     */
    private function bodyInput(): array
    {
        if (!$this->isJson()) {
            return $this->post;
        }

        $json = $this->json();

        return is_array($json) ? $json : [];
    }

    private function mediaType(
        string $headerValue,
    ): string {
        return strtolower(trim(explode(';', $headerValue, 2)[0]));
    }

    private function isJsonMediaType(
        string $mediaType,
    ): bool {
        return $mediaType === 'application/json' || str_ends_with($mediaType, '+json');
    }

    /**
     * Read a value by key, walking nested arrays for dot-notation keys when no literal key matches.
     */
    private static function dataGet(
        mixed $data,
        string $key,
        mixed $default = null,
    ): mixed {
        if (!is_array($data)) {
            return $default;
        }

        if (array_key_exists($key, $data)) {
            return $data[$key] ?? $default;
        }

        foreach (explode('.', $key) as $segment) {
            if (!is_array($data) || !array_key_exists($segment, $data)) {
                return $default;
            }

            $data = $data[$segment];
        }

        return $data ?? $default;
    }
}
