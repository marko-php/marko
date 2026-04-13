<?php

declare(strict_types=1);

namespace Marko\TailwindCss\Contracts;

interface TailwindEntrypointProviderInterface
{
    /**
     * @return array<string>
     */
    public function entrypoints(): array;
}
