<?php

declare(strict_types=1);

namespace Marko\Debugbar\Collectors;

use Marko\Debugbar\Debugbar;

interface CollectorInterface
{
    public function name(): string;

    /**
     * @return array<string, mixed>
     */
    public function collect(Debugbar $debugbar): array;
}
