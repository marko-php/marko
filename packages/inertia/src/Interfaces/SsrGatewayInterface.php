<?php

declare(strict_types=1);

namespace Marko\Inertia\Interfaces;

use Marko\Inertia\Ssr\SsrPage;

interface SsrGatewayInterface
{
    /**
     * @param array<string, mixed> $page
     */
    public function render(array $page): ?SsrPage;
}
