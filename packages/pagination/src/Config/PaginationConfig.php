<?php

declare(strict_types=1);

namespace Marko\Pagination\Config;

use Marko\Config\ConfigRepositoryInterface;

readonly class PaginationConfig
{
    public function __construct(
        private ConfigRepositoryInterface $config,
    ) {}

    public function perPage(): int
    {
        return $this->config->getInt('pagination.per_page');
    }

    public function maxPerPage(): int
    {
        return $this->config->getInt('pagination.max_per_page');
    }

    /**
     * Clamp a requested per-page value to the configured maximum.
     *
     * Returns a value between 1 and max_per_page.
     */
    public function clampPerPage(
        int $requested,
    ): int {
        return max(1, min($requested, $this->maxPerPage()));
    }
}
