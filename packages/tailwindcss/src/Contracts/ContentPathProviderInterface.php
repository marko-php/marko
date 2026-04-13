<?php

declare(strict_types=1);

namespace Marko\TailwindCss\Contracts;

interface ContentPathProviderInterface
{
    /**
     * @return array<string>
     */
    public function contentPaths(): array;
}
