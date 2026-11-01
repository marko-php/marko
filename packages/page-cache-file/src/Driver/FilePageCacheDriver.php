<?php

declare(strict_types=1);

namespace Marko\PageCache\File\Driver;

use FilesystemIterator;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Core\Path\ProjectPaths;
use Marko\PageCache\CacheKey;
use Marko\PageCache\CachePolicy;
use Marko\PageCache\Config\PageCacheConfig;
use Marko\PageCache\Contracts\PageCacheInterface;
use Marko\PageCache\Exceptions\PageCacheException;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Psr\Clock\ClockInterface;
use Random\RandomException;
use SplFileInfo;

/**
 * Stores pages as files under {path}/pages/{hash}.cache.
 *
 * Tag membership is a directory per tag holding one empty marker file per page
 * ({path}/tags/{tag-hash}/{page-hash}), so tagging a page is a single file create
 * with no shared index to read, rewrite or lock. The variants of each URL path are
 * tracked the same way under {path}/variants/{path-hash}/ to enforce
 * page-cache.max_variants_per_path.
 */
readonly class FilePageCacheDriver implements PageCacheInterface
{
    private const string HASH_PATTERN = '/^[0-9a-f]{32}$/';

    public function __construct(
        private PageCacheConfig $pageCache,
        private ProjectPaths $paths,
        private ClockInterface $clock,
    ) {}

    /**
     * @param array<string> $queryParams
     *
     * @throws ConfigNotFoundException
     */
    public function lookup(
        Request $request,
        array $queryParams,
    ): ?Response {
        $key = CacheKey::fromRequest($request, $queryParams);
        $path = $this->pagePath($key->hash());

        if (!file_exists($path)) {
            return null;
        }

        $content = file_get_contents($path);

        if ($content === false) {
            return null;
        }

        $data = unserialize($content, ['allowed_classes' => false]);

        if (!is_array($data)
            || !isset($data['status_code'], $data['body'], $data['headers'])
            || !array_key_exists('expires_at', $data)
            || !is_int($data['status_code'])
            || !is_string($data['body'])
            || !is_array($data['headers'])
            || !($data['expires_at'] === null || is_int($data['expires_at']))
        ) {
            return null;
        }

        // A null expiry means the entry never expires; it lives until purged by URL, by tag or cleared.
        if ($data['expires_at'] !== null && $data['expires_at'] < $this->clock->now()->getTimestamp()) {
            @unlink($path);

            return null;
        }

        return new Response(
            body: $data['body'],
            statusCode: $data['status_code'],
            headers: $data['headers'],
        );
    }

    /**
     * @throws ConfigNotFoundException|PageCacheException|RandomException
     */
    public function store(
        Request $request,
        Response $response,
        CachePolicy $policy,
    ): Response {
        $key = CacheKey::fromRequest($request, $policy->queryParams);
        $hash = $key->hash();

        if (!$this->claimVariant($key->path, $hash)) {
            return $response;
        }

        $ttl = $policy->ttl > 0 ? $policy->ttl : $this->pageCache->defaultTtl();
        $now = $this->clock->now()->getTimestamp();

        $data = [
            'status_code' => $response->statusCode(),
            'body' => $response->body(),
            'headers' => $response->headers(),
            'tags' => $policy->tags,
            'expires_at' => $ttl > 0 ? $now + $ttl : null,
            'created_at' => $now,
        ];

        $this->ensureDirectory($this->pagesDir());
        $this->atomicWrite($this->pagePath($hash), serialize($data));

        foreach ($policy->tags as $tag) {
            $tagDir = $this->tagDir($tag);
            $this->ensureDirectory($tagDir);
            touch($tagDir . '/' . $hash);
        }

        return $response;
    }

    /**
     * Record the page as a variant of its path, refusing it when the path already holds
     * page-cache.max_variants_per_path other entries.
     *
     * Markers whose page file is gone (expired, purged) are swept before refusing, so the
     * limit counts live entries. Concurrent stores may briefly overshoot the limit.
     *
     * @throws ConfigNotFoundException|PageCacheException
     */
    private function claimVariant(
        string $path,
        string $hash,
    ): bool {
        $max = $this->pageCache->maxVariantsPerPath();

        if ($max === 0) {
            return true;
        }

        $variantDir = $this->variantsDir() . '/' . hash('xxh128', $path);
        $marker = $variantDir . '/' . $hash;

        if (file_exists($marker)) {
            return true;
        }

        $this->ensureDirectory($variantDir);

        if ($this->countVariants($variantDir, $max, sweep: false) >= $max
            && $this->countVariants($variantDir, $max, sweep: true) >= $max
        ) {
            return false;
        }

        touch($marker);

        return true;
    }

    /**
     * Count variant markers in a directory, stopping at $max. With $sweep, markers whose page
     * file no longer exists are deleted instead of counted.
     *
     * @throws ConfigNotFoundException
     */
    private function countVariants(
        string $variantDir,
        int $max,
        bool $sweep,
    ): int {
        $count = 0;

        foreach (new FilesystemIterator($variantDir) as $marker) {
            /** @var SplFileInfo $marker */
            if ($sweep && !file_exists($this->pagePath($marker->getFilename()))) {
                @unlink($marker->getPathname());

                continue;
            }

            if (++$count >= $max) {
                break;
            }
        }

        return $count;
    }

    /**
     * Purge the GET entry for the URL over both http and https.
     *
     * An absolute URL purges its own host; a relative URL purges every exact (non-wildcard)
     * host listed in page-cache.trusted_hosts.
     *
     * @throws ConfigNotFoundException|PageCacheException
     */
    public function purgeUrl(string $url): bool
    {
        $parsed = parse_url($url);

        if ($parsed === false) {
            return false;
        }

        $path = $parsed['path'] ?? '/';
        $query = CacheKey::normalizeQuery($parsed['query'] ?? '');
        $hosts = $this->purgeHosts($url, $parsed);
        $success = true;

        foreach ($hosts as $host) {
            foreach (['http', 'https'] as $scheme) {
                $key = new CacheKey(
                    method: 'GET',
                    scheme: $scheme,
                    host: CacheKey::normalizeHost($host, $scheme),
                    path: $path,
                    query: $query,
                );
                $filePath = $this->pagePath($key->hash());

                if (file_exists($filePath) && !unlink($filePath)) {
                    $success = false;
                }
            }
        }

        return $success;
    }

    /**
     * @param array<string, int|string> $parsed
     * @return array<string>
     *
     * @throws ConfigNotFoundException|PageCacheException
     */
    private function purgeHosts(
        string $url,
        array $parsed,
    ): array {
        if (isset($parsed['host'])) {
            $host = (string) $parsed['host'];

            return [isset($parsed['port']) ? "$host:{$parsed['port']}" : $host];
        }

        $hosts = array_values(array_filter(
            $this->pageCache->trustedHosts(),
            static fn (string $host): bool => strpbrk($host, '*?[') === false,
        ));

        if ($hosts === []) {
            throw PageCacheException::purgeUrlWithoutHost($url);
        }

        return $hosts;
    }

    /**
     * Delete every page carrying the tag, then the tag's marker directory.
     *
     * @throws ConfigNotFoundException
     */
    public function purgeTag(string $tag): bool
    {
        $success = $this->purgeLegacyTagIndex($tag);
        $tagDir = $this->tagDir($tag);

        if (!is_dir($tagDir)) {
            return $success;
        }

        foreach (new FilesystemIterator($tagDir) as $marker) {
            /** @var SplFileInfo $marker */
            $this->deletePage($marker->getFilename());

            if (!@unlink($marker->getPathname()) && file_exists($marker->getPathname())) {
                $success = false;
            }
        }

        @rmdir($tagDir);

        return $success;
    }

    /**
     * Purge a serialized tag index written by earlier versions of this driver, so pages cached
     * before an upgrade are still purged by tag.
     *
     * @throws ConfigNotFoundException
     */
    private function purgeLegacyTagIndex(string $tag): bool
    {
        $indexPath = $this->tagsDir() . '/' . hash('xxh128', $tag) . '.tag';

        if (!file_exists($indexPath)) {
            return true;
        }

        $content = file_get_contents($indexPath);
        $decoded = is_string($content) && $content !== ''
            ? unserialize($content, ['allowed_classes' => false])
            : [];

        foreach (is_array($decoded) ? $decoded : [] as $hash) {
            if (is_string($hash)) {
                $this->deletePage($hash);
            }
        }

        return @unlink($indexPath) || !file_exists($indexPath);
    }

    /**
     * @throws ConfigNotFoundException
     */
    private function deletePage(string $hash): void
    {
        if (preg_match(self::HASH_PATTERN, $hash) !== 1) {
            return;
        }

        $pagePath = $this->pagePath($hash);

        if (file_exists($pagePath)) {
            @unlink($pagePath);
        }
    }

    /**
     * @throws ConfigNotFoundException
     */
    public function clear(): bool
    {
        $pagesDir = $this->pagesDir();

        if (is_dir($pagesDir)) {
            foreach (glob($pagesDir . '/*.cache') ?: [] as $file) {
                @unlink($file);
            }
        }

        $this->clearMarkerDirectories($this->tagsDir());
        $this->clearMarkerDirectories($this->variantsDir());

        return true;
    }

    /**
     * Remove the marker directories (and legacy *.tag index files) under a tags or variants directory.
     */
    private function clearMarkerDirectories(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (new FilesystemIterator($dir) as $entry) {
            /** @var SplFileInfo $entry */
            if (!$entry->isDir()) {
                @unlink($entry->getPathname());

                continue;
            }

            foreach (new FilesystemIterator($entry->getPathname()) as $marker) {
                /** @var SplFileInfo $marker */
                @unlink($marker->getPathname());
            }

            @rmdir($entry->getPathname());
        }
    }

    /**
     * @throws ConfigNotFoundException
     */
    private function resolvedPath(): string
    {
        return rtrim($this->paths->resolve($this->pageCache->path()), '/');
    }

    /**
     * @throws ConfigNotFoundException
     */
    private function pagesDir(): string
    {
        return $this->resolvedPath() . '/pages';
    }

    /**
     * @throws ConfigNotFoundException
     */
    private function pagePath(string $hash): string
    {
        return $this->pagesDir() . '/' . $hash . '.cache';
    }

    private function ensureDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    /**
     * @throws ConfigNotFoundException
     */
    private function tagsDir(): string
    {
        return $this->resolvedPath() . '/tags';
    }

    /**
     * @throws ConfigNotFoundException
     */
    private function tagDir(string $tag): string
    {
        return $this->tagsDir() . '/' . hash('xxh128', $tag);
    }

    /**
     * @throws ConfigNotFoundException
     */
    private function variantsDir(): string
    {
        return $this->resolvedPath() . '/variants';
    }

    /**
     * @throws RandomException
     */
    private function atomicWrite(
        string $path,
        string $content,
    ): void {
        $tmp = $path . '.tmp.' . bin2hex(random_bytes(8));
        file_put_contents($tmp, $content);
        rename($tmp, $path);
    }
}
