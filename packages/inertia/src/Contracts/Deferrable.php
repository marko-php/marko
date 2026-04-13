<?php

declare(strict_types=1);

namespace Marko\Inertia\Contracts;

interface Deferrable
{
    public function group(): string;
}
