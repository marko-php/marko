<?php

declare(strict_types=1);

namespace Marko\CodeIndexer\Contract;

interface IndexCacheInterface
{
    public function get(string $key): mixed;

    public function set(string $key, mixed $value): void;

    public function has(string $key): bool;

    public function invalidate(): void;
}
