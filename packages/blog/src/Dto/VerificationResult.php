<?php

declare(strict_types=1);

namespace Marko\Blog\Dto;

readonly class VerificationResult
{
    public function __construct(
        public string $browserToken,
        public string $postSlug,
        public int $commentId,
    ) {}
}
