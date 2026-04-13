<?php

declare(strict_types=1);

namespace Marko\Inertia\Contracts;

interface Mergeable
{
    public function shouldMerge(): bool;

    public function shouldDeepMerge(): bool;

    public function shouldPrepend(): bool;
}
