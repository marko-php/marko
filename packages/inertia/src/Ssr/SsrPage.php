<?php

declare(strict_types=1);

namespace Marko\Inertia\Ssr;

readonly class SsrPage
{
    /**
     * @param array<string> $head
     */
    public function __construct(
        public string $body,
        public array $head = [],
    ) {}
}
