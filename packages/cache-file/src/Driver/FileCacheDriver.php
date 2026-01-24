<?php

declare(strict_types=1);

namespace Marko\Cache\File\Driver;

use DateTimeImmutable;
use Marko\Cache\CacheItem;
use Marko\Cache\Config\CacheConfig;
use Marko\Cache\Contracts\CacheInterface;
use Marko\Cache\Contracts\CacheItemInterface;
use Marko\Cache\Exceptions\InvalidKeyException;

readonly class FileCacheDriver implements CacheInterface
{
    public function __construct(
        private CacheConfig $config,
    ) {}

    /**
     * @throws InvalidKeyException
     */
    public function get(
        string $key,
        mixed $default = null,
    ): mixed {
        $this->validateKey($key);

        $data = $this->read($key);

        if ($data === null) {
            return $default;
        }

        if ($this->isExpired($data)) {
            $this->delete($key);

            return $default;
        }

        return $data['value'];
    }

    /**
     * @throws InvalidKeyException
     */
    public function set(
        string $key,
        mixed $value,
        ?int $ttl = null,
    ): bool {
        $this->validateKey($key);
        $this->ensureDirectoryExists();

        $ttl ??= $this->config->defaultTtl();
        $expiresAt = $ttl > 0 ? time() + $ttl : null;

        $data = [
            'value' => $value,
            'expires_at' => $expiresAt,
            'created_at' => time(),
        ];

        return $this->write($key, $data);
    }

    /**
     * @throws InvalidKeyException
     */
    public function has(
        string $key,
    ): bool {
        $this->validateKey($key);

        $data = $this->read($key);

        if ($data === null) {
            return false;
        }

        if ($this->isExpired($data)) {
            $this->delete($key);

            return false;
        }

        return true;
    }

    /**
     * @throws InvalidKeyException
     */
    public function delete(
        string $key,
    ): bool {
        $this->validateKey($key);

        $filePath = $this->getFilePath($key);

        if (!file_exists($filePath)) {
            return true;
        }

        return unlink($filePath);
    }

    public function clear(): bool
    {
        if (!is_dir($this->config->path())) {
            return true;
        }

        $files = glob($this->config->path() . '/*.cache');

        if ($files === false) {
            return false;
        }

        $success = true;

        foreach ($files as $file) {
            if (!unlink($file)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * @throws InvalidKeyException
     */
    public function getItem(
        string $key,
    ): CacheItemInterface {
        $this->validateKey($key);

        $data = $this->read($key);

        if ($data === null) {
            return CacheItem::miss($key);
        }

        if ($this->isExpired($data)) {
            $this->delete($key);

            return CacheItem::miss($key);
        }

        $expiresAt = $data['expires_at'] !== null
            ? new DateTimeImmutable()->setTimestamp($data['expires_at'])
            : null;

        return CacheItem::hit($key, $data['value'], $expiresAt);
    }

    /**
     * @throws InvalidKeyException
     */
    public function getMultiple(
        array $keys,
        mixed $default = null,
    ): iterable {
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }

        return $result;
    }

    /**
     * @throws InvalidKeyException
     */
    public function setMultiple(
        array $values,
        ?int $ttl = null,
    ): bool {
        $success = true;

        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * @throws InvalidKeyException
     */
    public function deleteMultiple(
        array $keys,
    ): bool {
        $success = true;

        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * @throws InvalidKeyException
     */
    private function validateKey(
        string $key,
    ): void {
        if ($key === '') {
            throw InvalidKeyException::emptyKey();
        }

        if (!InvalidKeyException::isValidKey($key)) {
            throw InvalidKeyException::forKey($key);
        }
    }

    private function hashKey(
        string $key,
    ): string {
        return hash('xxh128', $key);
    }

    private function getFilePath(
        string $key,
    ): string {
        return $this->config->path() . '/' . $this->hashKey($key) . '.cache';
    }

    /**
     * @return array{value: mixed, expires_at: ?int, created_at: int}|null
     */
    private function read(
        string $key,
    ): ?array {
        $filePath = $this->getFilePath($key);

        if (!file_exists($filePath)) {
            return null;
        }

        $content = file_get_contents($filePath);

        if ($content === false) {
            return null;
        }

        $data = unserialize($content);

        if (!is_array($data) || !array_key_exists('value', $data) || !isset($data['created_at'])) {
            return null;
        }

        return $data;
    }

    /**
     * @param array{value: mixed, expires_at: ?int, created_at: int} $data
     */
    private function write(
        string $key,
        array $data,
    ): bool {
        $filePath = $this->getFilePath($key);
        $tempPath = $filePath . '.tmp.' . uniqid();

        $serialized = serialize($data);

        if (file_put_contents($tempPath, $serialized, LOCK_EX) === false) {
            return false;
        }

        return rename($tempPath, $filePath);
    }

    /**
     * @param array{value: mixed, expires_at: ?int, created_at: int} $data
     */
    private function isExpired(
        array $data,
    ): bool {
        if ($data['expires_at'] === null) {
            return false;
        }

        return time() > $data['expires_at'];
    }

    private function ensureDirectoryExists(): void
    {
        if (is_dir($this->config->path())) {
            return;
        }

        mkdir($this->config->path(), 0755, true);
    }
}
