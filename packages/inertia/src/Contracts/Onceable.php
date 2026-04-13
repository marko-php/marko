<?php

declare(strict_types=1);

namespace Marko\Inertia\Contracts;

interface Onceable
{
    public function key(): ?string;

    public function shouldRefresh(): bool;
}
