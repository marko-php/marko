<?php

declare(strict_types=1);

namespace Marko\PageCache\File\Driver;

use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\PageCache\CacheKey;
use Marko\PageCache\CachePolicy;
use Marko\PageCache\Config\PageCacheConfig;
use Marko\PageCache\Contracts\PageCacheInterface;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Random\RandomException;

readonly class FilePageCacheDriver implements PageCacheInterface
{
    public function __construct(
        private PageCacheConfig $pageCache,
    ) {}

    /**
     * @throws ConfigNotFoundException
     */
    public function lookup(Request $request): ?Response
    {
        $key = CacheKey::fromRequest($request);
        $path = $this->pagePath($key->hash());

        if (!file_exists($path)) {
            return null;
        }

        $content = file_get_contents($path);

        if ($content === false) {
            return null;
        }

        $data = unserialize($content);

        if (!is_array($data) || !isset($data['status_code'], $data['body'], $data['headers'], $data['expires_at'])) {
            return null;
        }

        if ($data['expires_at'] !== null && $data['expires_at'] < time()) {
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
     * @throws ConfigNotFoundException|RandomException
     */
    public function store(Request $request, Response $response, CachePolicy $policy): Response
    {
        $key = CacheKey::fromRequest($request);
        $ttl = $policy->ttl > 0 ? $policy->ttl : $this->pageCache->defaultTtl();
        $expiresAt = $ttl > 0 ? time() + $ttl : null;

        $data = [
            'status_code' => $response->statusCode(),
            'body' => $response->body(),
            'headers' => $response->headers(),
            'tags' => $policy->tags,
            'expires_at' => $expiresAt,
            'created_at' => time(),
        ];

        $this->ensureDirectory($this->pagesDir());
        $this->atomicWrite($this->pagePath($key->hash()), serialize($data));

        foreach ($policy->tags as $tag) {
            $this->ensureDirectory($this->tagsDir());
            $this->appendToTagIndex($this->tagPath($tag), $key->hash());
        }

        return $response;
    }

    /**
     * @throws ConfigNotFoundException
     */
    public function purgeUrl(string $url): bool
    {
        $parsed = parse_url($url);
        $path = $parsed['path'] ?? '/';
        $rawQuery = $parsed['query'] ?? '';
        $query = CacheKey::normalizeQuery($rawQuery);

        $key = new CacheKey(method: 'GET', path: $path, query: $query);
        $filePath = $this->pagePath($key->hash());

        if (!file_exists($filePath)) {
            return true;
        }

        return unlink($filePath);
    }

    /**
     * @throws ConfigNotFoundException
     */
    public function purgeTag(string $tag): bool
    {
        $tagIndexPath = $this->tagPath($tag);

        if (!file_exists($tagIndexPath)) {
            return true;
        }

        $fp = fopen($tagIndexPath, 'r+');
        if ($fp === false) {
            return false;
        }

        try {
            flock($fp, LOCK_EX);
            $content = stream_get_contents($fp);
            $hashes = $content !== false && $content !== '' ? unserialize($content) : [];

            foreach ($hashes as $hash) {
                $pagePath = $this->pagePath($hash);
                if (file_exists($pagePath)) {
                    @unlink($pagePath);
                }
            }

            ftruncate($fp, 0);
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }

        @unlink($tagIndexPath);
        return true;
    }

    /**
     * @throws ConfigNotFoundException
     */
    public function clear(): bool
    {
        $pagesDir = $this->pagesDir();

        if (!is_dir($pagesDir)) {
            return true;
        }

        $pageFiles = glob($pagesDir . '/*.cache') ?: [];
        foreach ($pageFiles as $file) {
            @unlink($file);
        }

        $tagsDir = $this->tagsDir();
        if (is_dir($tagsDir)) {
            $tagFiles = glob($tagsDir . '/*.tag') ?: [];
            foreach ($tagFiles as $file) {
                @unlink($file);
            }
        }

        return true;
    }

    /**
     * @throws ConfigNotFoundException
     */
    private function pagesDir(): string
    {
        return $this->pageCache->path() . '/pages';
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
        return $this->pageCache->path() . '/tags';
    }

    /**
     * @throws ConfigNotFoundException
     */
    private function tagPath(string $tag): string
    {
        return $this->tagsDir() . '/' . hash('xxh128', $tag) . '.tag';
    }

    private function appendToTagIndex(string $tagIndexPath, string $pageHash): void
    {
        $fp = fopen($tagIndexPath, 'cb+');
        if ($fp === false) {
            return;
        }
        try {
            flock($fp, LOCK_EX);
            $existing = stream_get_contents($fp);
            $hashes = $existing !== false && $existing !== ''
                ? unserialize($existing)
                : [];
            $hashes = array_values(array_unique([...$hashes, $pageHash]));
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, serialize($hashes));
            fflush($fp);
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    /**
     * @throws RandomException
     */
    private function atomicWrite(string $path, string $content): void
    {
        $tmp = $path . '.tmp.' . bin2hex(random_bytes(8));
        file_put_contents($tmp, $content);
        rename($tmp, $path);
    }
}
