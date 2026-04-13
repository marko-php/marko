<?php

declare(strict_types=1);

namespace Marko\Vite\ValueObjects;

readonly class FilePublishResult
{
    public function __construct(
        public string $path,
        public string $status,
        public ?string $message = null,
    ) {}
}
