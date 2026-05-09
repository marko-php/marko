<?php

declare(strict_types=1);

namespace Marko\PageCache;

use Marko\Routing\Http\Request;

readonly class CacheKey
{
    public function __construct(
        public string $method,
        public string $path,
        public string $query,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $queryArray = $request->query();
        ksort($queryArray);

        return new self(
            method: $request->method(),
            path: $request->path(),
            query: http_build_query($queryArray),
        );
    }

    public static function normalizeQuery(string $rawQuery): string
    {
        if ($rawQuery === '') {
            return '';
        }

        $rawForParse = str_replace('+', '%2B', $rawQuery);
        parse_str($rawForParse, $queryArray);
        ksort($queryArray);

        return http_build_query($queryArray, '', '&', PHP_QUERY_RFC3986);
    }

    public function hash(): string
    {
        return hash('xxh128', "$this->method|$this->path|$this->query");
    }
}
