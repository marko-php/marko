<?php

declare(strict_types=1);

namespace Marko\Inertia\Ssr;

interface SsrTransportInterface
{
    public function post(string $url, string $body): ?string;
}
